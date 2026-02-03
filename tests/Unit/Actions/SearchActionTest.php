<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\LaravelFastCrud\Actions\Search;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use Psr\Http\Message\ResponseInterface;

final class SearchActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfig();
    }

    private function createController(array $data = [], array $paginationData = []): object
    {
        return new class ($data, $paginationData) {
            use Search;

            public ?Builder $modifiedQuery = null;
            public array $afterSearchData = [];
            public bool $answerSuccessCalled = false;
            public array $responseData = [];
            public array $responseMeta = [];
            public string $modelClass = '';
            private array $mockData;
            private array $mockPaginationData;

            public function __construct(array $data = [], array $paginationData = [])
            {
                $this->mockData = $data ?: [['id' => 1, 'name' => 'Test']];
                $this->mockPaginationData = $paginationData ?: [
                    'current' => 1,
                    'perPage' => 40,
                    'pagesCount' => 1,
                    'count' => 1,
                ];
            }

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifySearchQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function afterSearch(array $data): void
            {
                $this->afterSearchData = $data;
            }

            protected function answerSuccess(array $data, array $meta = []): JsonResponse|ResponseInterface
            {
                $this->answerSuccessCalled = true;
                $this->responseData = $data;
                $this->responseMeta = $meta;
                return new JsonResponse(['status' => 'success', 'data' => $data, 'meta' => $meta]);
            }

            public function buildPagination(Builder $query, int $perPage = 40, string $method = 'toArray'): void
            {
                $this->data = $this->mockData;
                $this->paginationData = $this->mockPaginationData;
            }
        };
    }

    public function testSearchCallsModifySearchQueryHook(): void
    {
        $this->controller = $this->createController();
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $this->controller->search($request);

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testSearchCallsAfterSearchHook(): void
    {
        $this->controller = $this->createController();
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $this->controller->search($request);

        $this->assertNotEmpty($this->controller->afterSearchData);
        $this->assertEquals([['id' => 1, 'name' => 'Test']], $this->controller->afterSearchData);
    }

    public function testSearchReturnsJsonResponse(): void
    {
        $this->controller = $this->createController();
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->search($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertTrue($this->controller->answerSuccessCalled);
    }

    public function testSearchReturnsDataWithPaginationMeta(): void
    {
        $this->controller = $this->createController();
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $this->controller->search($request);

        $this->assertArrayHasKey('pagination', $this->controller->responseMeta);
        $this->assertEquals(1, $this->controller->responseMeta['pagination']['current']);
        $this->assertEquals(40, $this->controller->responseMeta['pagination']['perPage']);
    }

    public function testSearchUsesCustomPerPage(): void
    {
        $customPagination = [
            'current' => 1,
            'perPage' => 20,
            'pagesCount' => 5,
            'count' => 100,
        ];
        $this->controller = $this->createController([], $customPagination);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock(perPage: 20);

        $this->controller->search($request);

        $this->assertEquals(20, $this->controller->responseMeta['pagination']['perPage']);
    }

    public function testSearchWithCustomMethod(): void
    {
        $this->controller = $this->createController();
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->search($request, 'toSoftArray');

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testSearchReturnsCorrectDataStructure(): void
    {
        $testData = [
            ['id' => 1, 'name' => 'Product 1'],
            ['id' => 2, 'name' => 'Product 2'],
        ];
        $this->controller = $this->createController($testData);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->search($request);

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertCount(2, $responseData['data']);
    }

    private function createRequestMock(
        int $perPage = 40,
        array $filters = [],
        string $sort = ''
    ): Request&MockInterface {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('perPage', Mockery::any())->andReturn($perPage);
        $request->shouldReceive('get')->with('filter', [])->andReturn($filters);
        $request->shouldReceive('input')->with('sort', '')->andReturn($sort);

        return $request;
    }

    private function createBuilderMock(): Builder&MockInterface
    {
        $connectionMock = Mockery::mock('Illuminate\Database\Connection');
        $connectionMock->shouldReceive('getDriverName')->andReturn('mysql');

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('getConnection')->andReturn($connectionMock);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereIn')->andReturnSelf();
        $builderMock->shouldReceive('orderBy')->andReturnSelf();
        $builderMock->shouldReceive('orderByDesc')->andReturnSelf();

        return $builderMock;
    }

    private function createMockModelClass(Builder $builderMock): string
    {
        $className = 'TestSearchModel' . uniqid();

        eval("
            namespace DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions;

            class {$className} extends \\Illuminate\\Database\\Eloquent\\Model {
                public static \$builderMock;

                public static function query() {
                    return self::\$builderMock;
                }
            }
        ");

        $fullClassName = "DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions\\{$className}";
        $fullClassName::$builderMock = $builderMock;

        return $fullClassName;
    }

    private function mockConfig(array $overrides = []): void
    {
        $defaults = [
            'devToolbelt.fast-crud.search.method' => 'toArray',
            'devToolbelt.fast-crud.search.per_page' => 40,
        ];

        $config = array_merge($defaults, $overrides);

        if (!function_exists('DevToolbelt\LaravelFastCrud\Actions\config')) {
            eval('
                namespace DevToolbelt\LaravelFastCrud\Actions;

                function config($key, $default = null) {
                    static $config = [];
                    if (is_array($key)) {
                        $config = array_merge($config, $key);
                        return null;
                    }
                    return $config[$key] ?? $default;
                }
            ');
        }

        \DevToolbelt\LaravelFastCrud\Actions\config($config);
    }
}
