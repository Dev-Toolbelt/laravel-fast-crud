<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Traits;

use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use DevToolbelt\LaravelFastCrud\Traits\Pageable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;

final class PageableTest extends TestCase
{
    private object $pageable;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createPageable(bool $skipPagination = false): object
    {
        return new class ($skipPagination) {
            use Pageable {
                buildPagination as traitBuildPagination;
            }

            private bool $skipPagination;

            public function __construct(bool $skipPagination)
            {
                $this->skipPagination = $skipPagination;
            }

            public function buildPagination(Builder $query, int $perPage = 40, string $method = 'toArray'): void
            {
                $this->data = [];
                $this->paginationData = [];

                if ($this->skipPagination) {
                    $this->buildWithoutPaginationPublic($query, $method);
                    return;
                }

                $this->buildWithPaginationPublic($query, $perPage, $method);
            }

            public function buildWithoutPaginationPublic(Builder $query, string $method): void
            {
                $query->get()->each(function (Model $row) use ($method): void {
                    $this->data[] = $row->$method();
                });
            }

            public function buildWithPaginationPublic(Builder $query, int $perPage, string $method): void
            {
                $count = $query->count();
                $paginate = $query->paginate($perPage);

                $this->data = array_map(
                    static fn(Model $model): array => $model->$method(),
                    $paginate->items()
                );

                $this->paginationData = [
                    'current' => $paginate->currentPage(),
                    'perPage' => $paginate->perPage(),
                    'pagesCount' => (int) ceil($count / $paginate->perPage()),
                    'count' => $count,
                ];
            }

            public function getData(): array
            {
                return $this->data;
            }

            public function getPaginationData(): array
            {
                return $this->paginationData;
            }
        };
    }

    public function testBuildPaginationWithPagination(): void
    {
        $this->pageable = $this->createPageable(skipPagination: false);

        $model1 = $this->createModelMock(['id' => 1, 'name' => 'Test 1']);
        $model2 = $this->createModelMock(['id' => 2, 'name' => 'Test 2']);

        $paginator = new LengthAwarePaginator(
            [$model1, $model2],
            10,
            5,
            1
        );

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('count')->once()->andReturn(10);
        $queryMock->shouldReceive('paginate')->with(5)->once()->andReturn($paginator);

        $this->pageable->buildPagination($queryMock, 5, 'toArray');

        $data = $this->pageable->getData();
        $paginationData = $this->pageable->getPaginationData();

        $this->assertCount(2, $data);
        $this->assertEquals(['id' => 1, 'name' => 'Test 1'], $data[0]);
        $this->assertEquals(['id' => 2, 'name' => 'Test 2'], $data[1]);
        $this->assertEquals(1, $paginationData['current']);
        $this->assertEquals(5, $paginationData['perPage']);
        $this->assertEquals(2, $paginationData['pagesCount']);
        $this->assertEquals(10, $paginationData['count']);
    }

    public function testBuildPaginationWithoutPagination(): void
    {
        $this->pageable = $this->createPageable(skipPagination: true);

        $model1 = $this->createModelMock(['id' => 1, 'name' => 'Test 1']);
        $model2 = $this->createModelMock(['id' => 2, 'name' => 'Test 2']);
        $model3 = $this->createModelMock(['id' => 3, 'name' => 'Test 3']);

        $collection = new Collection([$model1, $model2, $model3]);

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('get')->once()->andReturn($collection);

        $this->pageable->buildPagination($queryMock, 5, 'toArray');

        $data = $this->pageable->getData();
        $paginationData = $this->pageable->getPaginationData();

        $this->assertCount(3, $data);
        $this->assertEmpty($paginationData);
    }

    public function testBuildPaginationWithDefaultPerPage(): void
    {
        $this->pageable = $this->createPageable(skipPagination: false);

        $model = $this->createModelMock(['id' => 1, 'name' => 'Test']);

        $paginator = new LengthAwarePaginator(
            [$model],
            1,
            40,
            1
        );

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('count')->once()->andReturn(1);
        $queryMock->shouldReceive('paginate')->with(40)->once()->andReturn($paginator);

        $this->pageable->buildPagination($queryMock);

        $paginationData = $this->pageable->getPaginationData();

        $this->assertEquals(40, $paginationData['perPage']);
    }

    public function testBuildPaginationCalculatesPagesCountCorrectly(): void
    {
        $this->pageable = $this->createPageable(skipPagination: false);

        $model = $this->createModelMock(['id' => 1, 'name' => 'Test']);

        $paginator = new LengthAwarePaginator(
            [$model],
            100,
            15,
            1
        );

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('count')->once()->andReturn(100);
        $queryMock->shouldReceive('paginate')->with(15)->once()->andReturn($paginator);

        $this->pageable->buildPagination($queryMock, 15, 'toArray');

        $paginationData = $this->pageable->getPaginationData();

        // 100 / 15 = 6.67, ceil = 7
        $this->assertEquals(7, $paginationData['pagesCount']);
    }

    public function testBuildPaginationWithEmptyResults(): void
    {
        $this->pageable = $this->createPageable(skipPagination: false);

        $paginator = new LengthAwarePaginator(
            [],
            0,
            40,
            1
        );

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('count')->once()->andReturn(0);
        $queryMock->shouldReceive('paginate')->with(40)->once()->andReturn($paginator);

        $this->pageable->buildPagination($queryMock);

        $data = $this->pageable->getData();
        $paginationData = $this->pageable->getPaginationData();

        $this->assertEmpty($data);
        $this->assertEquals(0, $paginationData['count']);
    }

    public function testBuildPaginationWithCustomMethod(): void
    {
        $this->pageable = $this->createPageable(skipPagination: false);

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('toSoftArray')->once()->andReturn(['id' => 1, 'name' => 'Soft']);

        $paginator = new LengthAwarePaginator(
            [$model],
            1,
            40,
            1
        );

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('count')->once()->andReturn(1);
        $queryMock->shouldReceive('paginate')->with(40)->once()->andReturn($paginator);

        $this->pageable->buildPagination($queryMock, 40, 'toSoftArray');

        $data = $this->pageable->getData();

        $this->assertEquals(['id' => 1, 'name' => 'Soft'], $data[0]);
    }

    public function testBuildWithoutPaginationIteratesAllRecords(): void
    {
        $this->pageable = $this->createPageable(skipPagination: true);

        $models = [];
        for ($i = 1; $i <= 100; $i++) {
            $models[] = $this->createModelMock(['id' => $i, 'name' => "Test $i"]);
        }

        $collection = new Collection($models);

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('get')->once()->andReturn($collection);

        $this->pageable->buildPagination($queryMock);

        $data = $this->pageable->getData();

        $this->assertCount(100, $data);
        $this->assertEquals(['id' => 1, 'name' => 'Test 1'], $data[0]);
        $this->assertEquals(['id' => 100, 'name' => 'Test 100'], $data[99]);
    }

    public function testBuildPaginationResetsDataOnEachCall(): void
    {
        $this->pageable = $this->createPageable(skipPagination: false);

        $model = $this->createModelMock(['id' => 1, 'name' => 'Test']);

        $paginator = new LengthAwarePaginator(
            [$model],
            1,
            40,
            1
        );

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('count')->andReturn(1);
        $queryMock->shouldReceive('paginate')->with(40)->andReturn($paginator);

        // First call - data will be reset
        $this->pageable->buildPagination($queryMock);

        $data = $this->pageable->getData();

        // Data should be the new data
        $this->assertEquals([['id' => 1, 'name' => 'Test']], $data);
    }

    public function testBuildPaginationWithSinglePageResults(): void
    {
        $this->pageable = $this->createPageable(skipPagination: false);

        $model = $this->createModelMock(['id' => 1, 'name' => 'Test']);

        $paginator = new LengthAwarePaginator(
            [$model],
            5,
            10,
            1
        );

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('count')->once()->andReturn(5);
        $queryMock->shouldReceive('paginate')->with(10)->once()->andReturn($paginator);

        $this->pageable->buildPagination($queryMock, 10);

        $paginationData = $this->pageable->getPaginationData();

        $this->assertEquals(1, $paginationData['pagesCount']);
        $this->assertEquals(5, $paginationData['count']);
    }

    private function createModelMock(array $data): Model&MockInterface
    {
        $model = Mockery::mock(Model::class);
        $model->shouldReceive('toArray')->andReturn($data);
        return $model;
    }
}
