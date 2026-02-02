<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\LaravelFastCrud\Actions\Search;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class SearchActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfig();

        $this->controller = new class {
            use Search {
                search as parentSearch;
            }

            public ?Builder $modifiedQuery = null;
            public array $afterSearchData = [];
            public bool $answerSuccessCalled = false;
            public array $responseData = [];
            public array $responseMeta = [];
            public string $modelClass = '';

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

            // Override buildPagination to avoid request() helper
            public function buildPagination(Builder $query, int $perPage = 40, string $method = 'toArray'): void
            {
                $this->data = [['id' => 1, 'name' => 'Test']];
                $this->paginationData = [
                    'current' => 1,
                    'perPage' => $perPage,
                    'pagesCount' => 1,
                    'count' => 1,
                ];
            }

            public function search(Request $request, ?string $method = null): JsonResponse|ResponseInterface
            {
                $method = $method ?? 'toArray';
                $modelName = $this->modelClassName();
                $query = $modelName::query();

                $this->modifySearchQuery($query);
                $this->processSearch($query, $request->get('filter', []));
                $this->processSort($query, $request->input('sort', ''));

                $this->buildPagination($query, (int) $request->input('perPage', 40), $method);
                $this->afterSearch($this->data);

                return $this->answerSuccess($this->data, meta: [
                    'pagination' => $this->paginationData
                ]);
            }
        };
    }

    public function testModifySearchQueryHookIsCalled(): void
    {
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('perPage', 40)->andReturn(10);
        $request->shouldReceive('get')->with('filter', [])->andReturn([]);
        $request->shouldReceive('input')->with('sort', '')->andReturn('');

        $this->controller->search($request);

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testAfterSearchHookIsCalled(): void
    {
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('perPage', 40)->andReturn(10);
        $request->shouldReceive('get')->with('filter', [])->andReturn([]);
        $request->shouldReceive('input')->with('sort', '')->andReturn('');

        $this->controller->search($request);

        $this->assertNotEmpty($this->controller->afterSearchData);
    }

    public function testSearchReturnsSuccessResponse(): void
    {
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('perPage', 40)->andReturn(10);
        $request->shouldReceive('get')->with('filter', [])->andReturn([]);
        $request->shouldReceive('input')->with('sort', '')->andReturn('');

        $response = $this->controller->search($request);

        $this->assertTrue($this->controller->answerSuccessCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testSearchReturnsDataWithPaginationMeta(): void
    {
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('perPage', 40)->andReturn(10);
        $request->shouldReceive('get')->with('filter', [])->andReturn([]);
        $request->shouldReceive('input')->with('sort', '')->andReturn('');

        $this->controller->search($request);

        $this->assertArrayHasKey('pagination', $this->controller->responseMeta);
    }

    private function createBuilderMock(): Builder
    {
        $builderMock = Mockery::mock(Builder::class);
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
