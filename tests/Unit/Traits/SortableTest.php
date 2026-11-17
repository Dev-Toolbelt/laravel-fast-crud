<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Traits;

use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use DevToolbelt\LaravelFastCrud\Traits\Sortable;
use Illuminate\Database\Eloquent\Builder;
use Mockery;

final class SortableTest extends TestCase
{
    use Sortable;

    private Builder $queryMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryMock = Mockery::mock(Builder::class);
    }

    public function testProcessSortWithEmptyString(): void
    {
        $this->queryMock->shouldNotReceive('orderBy');
        $this->processSort($this->queryMock, '');
        $this->addToAssertionCount(1);
    }

    public function testProcessSortWithSingleColumnAscending(): void
    {
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('name', 'asc')
            ->andReturnSelf();

        $this->processSort($this->queryMock, 'name');
        $this->addToAssertionCount(1);
    }

    public function testProcessSortWithSingleColumnDescending(): void
    {
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('created_at', 'desc')
            ->andReturnSelf();

        $this->processSort($this->queryMock, '-created_at');
        $this->addToAssertionCount(1);
    }

    public function testProcessSortWithMultipleColumns(): void
    {
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('category', 'asc')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('price', 'desc')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('name', 'asc')
            ->andReturnSelf();

        $this->processSort($this->queryMock, 'category,-price,name');
        $this->addToAssertionCount(1);
    }

    public function testProcessSortConvertsCamelCaseToSnakeCase(): void
    {
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('created_at', 'asc')
            ->andReturnSelf();

        $this->processSort($this->queryMock, 'createdAt');
        $this->addToAssertionCount(1);
    }

    public function testProcessSortConvertsCamelCaseToSnakeCaseDescending(): void
    {
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('updated_at', 'desc')
            ->andReturnSelf();

        $this->processSort($this->queryMock, '-updatedAt');
        $this->addToAssertionCount(1);
    }

    public function testProcessSortIgnoresEmptyColumns(): void
    {
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('name', 'asc')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('price', 'asc')
            ->andReturnSelf();

        $this->processSort($this->queryMock, 'name,,price');
        $this->addToAssertionCount(1);
    }

    public function testProcessSortTrimsWhitespace(): void
    {
        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('name', 'asc')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('orderBy')
            ->once()
            ->with('price', 'desc')
            ->andReturnSelf();

        $this->processSort($this->queryMock, ' name , -price ');
        $this->addToAssertionCount(1);
    }
}
