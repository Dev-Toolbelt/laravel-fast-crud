<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use DevToolbelt\LaravelFastCrud\Actions\Options;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class OptionsActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfig();

        $this->controller = new class {
            use Options;

            public ?QueryBuilder $modifiedQuery = null;
            public array $afterOptionsData = [];
            public bool $answerRequiredCalled = false;
            public ?HttpStatusCode $requiredStatusCode = null;
            public string $requiredField = '';
            public bool $answerColumnNotFoundCalled = false;
            public ?HttpStatusCode $columnNotFoundStatusCode = null;
            public string $columnNotFoundField = '';
            public bool $answerSuccessCalled = false;
            public array $responseData = [];
            public string $modelClass = '';

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifyOptionsQuery(Builder|QueryBuilder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function afterOptions(array $rows): void
            {
                $this->afterOptionsData = $rows;
            }

            public function hasModelAttribute(Model $model, string $attributeName): bool
            {
                return in_array($attributeName, ['name', 'title', 'external_id', 'id', 'deleted_at']);
            }

            protected function answerRequired(
                string $field,
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                $this->answerRequiredCalled = true;
                $this->requiredField = $field;
                $this->requiredStatusCode = $code;
                return new JsonResponse(['status' => 'fail', 'message' => "$field is required"], $code->value);
            }

            protected function answerColumnNotFound(
                string $field,
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                $this->answerColumnNotFoundCalled = true;
                $this->columnNotFoundField = $field;
                $this->columnNotFoundStatusCode = $code;
                return new JsonResponse(['status' => 'fail', 'message' => "Column $field not found"], $code->value);
            }

            protected function answerSuccess(
                mixed $data,
                HttpStatusCode $code = HttpStatusCode::OK,
                array $meta = []
            ): JsonResponse|ResponseInterface {
                $this->answerSuccessCalled = true;
                $this->responseData = $data;
                return new JsonResponse(['status' => 'success', 'data' => $data], $code->value);
            }
        };
    }

    public function testOptionsReturnsRequiredWhenLabelMissing(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn(null);

        $this->controller->options($request);

        $this->assertTrue($this->controller->answerRequiredCalled);
        $this->assertSame('label', $this->controller->requiredField);
    }

    public function testOptionsReturnsColumnNotFoundForInvalidLabel(): void
    {
        $modelClass = $this->createMockModelClass();
        $this->controller = new class {
            use Options;

            public ?QueryBuilder $modifiedQuery = null;
            public bool $answerColumnNotFoundCalled = false;
            public string $columnNotFoundField = '';
            public string $modelClass = '';

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifyOptionsQuery(Builder|QueryBuilder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function afterOptions(array $rows): void
            {
            }

            public function hasModelAttribute(Model $model, string $attributeName): bool
            {
                return false;
            }

            protected function answerRequired(string $field): JsonResponse|ResponseInterface
            {
                return new JsonResponse(['status' => 'fail'], 400);
            }

            protected function answerColumnNotFound(string $field): JsonResponse|ResponseInterface
            {
                $this->answerColumnNotFoundCalled = true;
                $this->columnNotFoundField = $field;
                return new JsonResponse(['status' => 'fail'], 400);
            }

            protected function answerSuccess(array $data, array $meta = []): JsonResponse|ResponseInterface
            {
                return new JsonResponse(['status' => 'success', 'data' => $data]);
            }
        };

        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('invalid_column');

        $this->controller->options($request);

        $this->assertTrue($this->controller->answerColumnNotFoundCalled);
        $this->assertSame('invalid_column', $this->controller->columnNotFoundField);
    }

    public function testModifyOptionsQueryHookIsCalled(): void
    {
        $modelClass = $this->createMockModelClass();
        $this->controller->setModelClass($modelClass);
        $this->mockDbFacade();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testAfterOptionsHookIsCalled(): void
    {
        $modelClass = $this->createMockModelClass();
        $this->controller->setModelClass($modelClass);
        $this->mockDbFacade();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertIsArray($this->controller->afterOptionsData);
    }

    public function testOptionsReturnsSuccessResponse(): void
    {
        $modelClass = $this->createMockModelClass();
        $this->controller->setModelClass($modelClass);
        $this->mockDbFacade();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $response = $this->controller->options($request);

        $this->assertTrue($this->controller->answerSuccessCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testOptionsReturnsFormattedLabelValuePairs(): void
    {
        $modelClass = $this->createMockModelClass();
        $this->controller->setModelClass($modelClass);
        $this->mockDbFacade();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertNotEmpty($this->controller->responseData);
        $this->assertArrayHasKey('label', $this->controller->responseData[0]);
        $this->assertArrayHasKey('value', $this->controller->responseData[0]);
    }

    public function testOptionsUsesHttpStatusFromConfig(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.options.http_status' => HttpStatusCode::ACCEPTED->value,
        ]);

        $modelClass = $this->createMockModelClass();
        $this->controller->setModelClass($modelClass);
        $this->mockDbFacade();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $response = $this->controller->options($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(HttpStatusCode::ACCEPTED->value, $response->getStatusCode());
    }

    public function testOptionsUsesDefaultHttpStatusWhenNotConfigured(): void
    {
        $this->mockConfig([]);

        $modelClass = $this->createMockModelClass();
        $this->controller->setModelClass($modelClass);
        $this->mockDbFacade();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $response = $this->controller->options($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(HttpStatusCode::OK->value, $response->getStatusCode());
    }

    public function testOptionsRequiredUsesValidationHttpStatusFromConfig(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.global.validation.http_status' => HttpStatusCode::UNPROCESSABLE_ENTITY->value,
        ]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn(null);

        $response = $this->controller->options($request);

        $this->assertTrue($this->controller->answerRequiredCalled);
        $this->assertSame(HttpStatusCode::UNPROCESSABLE_ENTITY, $this->controller->requiredStatusCode);
        $this->assertSame(HttpStatusCode::UNPROCESSABLE_ENTITY->value, $response->getStatusCode());
    }

    public function testOptionsRequiredUsesDefaultValidationHttpStatus(): void
    {
        $this->mockConfig([]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn(null);

        $response = $this->controller->options($request);

        $this->assertTrue($this->controller->answerRequiredCalled);
        $this->assertSame(HttpStatusCode::BAD_REQUEST, $this->controller->requiredStatusCode);
        $this->assertSame(HttpStatusCode::BAD_REQUEST->value, $response->getStatusCode());
    }

    public function testOptionsFiltersSoftDeletedRecords(): void
    {
        $modelClass = $this->createMockModelClass();
        $this->controller->setModelClass($modelClass);
        $queryBuilderMock = $this->mockDbFacade(withWhereNull: false);

        $queryBuilderMock->shouldReceive('whereNull')
            ->with('deleted_at')
            ->once()
            ->andReturnSelf();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertTrue($this->controller->answerSuccessCalled);
    }

    public function testOptionsDoesNotFilterSoftDeleteWhenModelLacksDeletedAt(): void
    {
        $modelClass = $this->createMockModelClass();

        // Controller where hasModelAttribute returns false for deleted_at
        $this->controller = new class {
            use Options;

            public ?QueryBuilder $modifiedQuery = null;
            public array $afterOptionsData = [];
            public bool $answerSuccessCalled = false;
            public array $responseData = [];
            public string $modelClass = '';

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifyOptionsQuery(Builder|QueryBuilder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function afterOptions(array $rows): void
            {
                $this->afterOptionsData = $rows;
            }

            public function hasModelAttribute(Model $model, string $attributeName): bool
            {
                // Has label/value columns but NOT deleted_at
                return in_array($attributeName, ['name', 'title', 'external_id', 'id']);
            }

            protected function answerRequired(
                string $field,
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                return new JsonResponse(['status' => 'fail', 'message' => "$field is required"], $code->value);
            }

            protected function answerColumnNotFound(
                string $field,
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                return new JsonResponse(['status' => 'fail', 'message' => "Column $field not found"], $code->value);
            }

            protected function answerSuccess(
                mixed $data,
                HttpStatusCode $code = HttpStatusCode::OK,
                array $meta = []
            ): JsonResponse|ResponseInterface {
                $this->answerSuccessCalled = true;
                $this->responseData = $data;
                return new JsonResponse(['status' => 'success', 'data' => $data], $code->value);
            }
        };

        $this->controller->setModelClass($modelClass);
        $queryBuilderMock = $this->mockDbFacade();

        // whereNull should NOT be called since model lacks deleted_at
        $queryBuilderMock->shouldNotReceive('whereNull');

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertTrue($this->controller->answerSuccessCalled);
    }

    public function testOptionsUsesCustomDeletedAtFieldFromConfig(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.soft_delete.deleted_at_field' => 'removed_at',
        ]);

        // Controller where hasModelAttribute returns true for removed_at
        $this->controller = new class {
            use Options;

            public ?QueryBuilder $modifiedQuery = null;
            public array $afterOptionsData = [];
            public bool $answerSuccessCalled = false;
            public array $responseData = [];
            public string $modelClass = '';

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifyOptionsQuery(Builder|QueryBuilder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function afterOptions(array $rows): void
            {
                $this->afterOptionsData = $rows;
            }

            public function hasModelAttribute(Model $model, string $attributeName): bool
            {
                return in_array($attributeName, ['name', 'title', 'id', 'removed_at']);
            }

            protected function answerRequired(
                string $field,
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                return new JsonResponse(['status' => 'fail', 'message' => "$field is required"], $code->value);
            }

            protected function answerColumnNotFound(
                string $field,
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                return new JsonResponse(['status' => 'fail', 'message' => "Column $field not found"], $code->value);
            }

            protected function answerSuccess(
                mixed $data,
                HttpStatusCode $code = HttpStatusCode::OK,
                array $meta = []
            ): JsonResponse|ResponseInterface {
                $this->answerSuccessCalled = true;
                $this->responseData = $data;
                return new JsonResponse(['status' => 'success', 'data' => $data], $code->value);
            }
        };

        $modelClass = $this->createMockModelClass();
        $this->controller->setModelClass($modelClass);
        $queryBuilderMock = $this->mockDbFacade(withWhereNull: false);

        $queryBuilderMock->shouldReceive('whereNull')
            ->with('removed_at')
            ->once()
            ->andReturnSelf();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertTrue($this->controller->answerSuccessCalled);
    }

    private function createMockModelClass(): string
    {
        $className = 'TestOptionsModel' . uniqid();

        eval("
            namespace DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions;

            class {$className} extends \\Illuminate\\Database\\Eloquent\\Model {
                public function __construct() {
                    // Don't call parent
                }

                public function getTable() {
                    return 'test_table';
                }

                public function getConnectionName() {
                    return 'testing';
                }

                public function getFillable() {
                    return ['name', 'title'];
                }

                public function getGuarded() {
                    return [];
                }

                public function getOriginal(\$key = null, \$default = null) {
                    return [];
                }

                public function getCasts() {
                    return [];
                }

                public function getAppends() {
                    return [];
                }
            }
        ");

        return "DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions\\{$className}";
    }

    private function mockDbFacade(bool $withWhereNull = true): QueryBuilder
    {
        $queryBuilderMock = Mockery::mock(QueryBuilder::class);
        $queryBuilderMock->shouldReceive('select')->andReturnSelf();
        $queryBuilderMock->shouldReceive('orderBy')->andReturnSelf();

        if ($withWhereNull) {
            $queryBuilderMock->shouldReceive('whereNull')->andReturnSelf();
        }

        $queryBuilderMock->shouldReceive('get')->andReturn(collect([
            (object) ['label' => 'Option 1', 'value' => '1'],
            (object) ['label' => 'Option 2', 'value' => '2'],
        ]));

        $connectionMock = Mockery::mock(\Illuminate\Database\Connection::class);
        $connectionMock->shouldReceive('table')->andReturn($queryBuilderMock);

        DB::swap(new class ($connectionMock) {
            private $connectionMock;

            public function __construct($connectionMock)
            {
                $this->connectionMock = $connectionMock;
            }

            public function connection($name = null)
            {
                return $this->connectionMock;
            }
        });

        return $queryBuilderMock;
    }

    private function mockConfig(array $overrides = []): void
    {
        $defaults = [
            'devToolbelt.fast-crud.options.default_value' => 'id',
        ];

        $config = array_merge($defaults, $overrides);

        if (!function_exists('DevToolbelt\LaravelFastCrud\Actions\config')) {
            eval('
                namespace DevToolbelt\LaravelFastCrud\Actions;

                function config($key, $default = null) {
                    static $config = [];
                    if (is_array($key)) {
                        $config = $key;
                        return null;
                    }
                    // If Laravel is available, always use its config (for integration tests)
                    if (function_exists("app") && \app()->bound("config")) {
                        return \config($key, $default);
                    }
                    // Otherwise use local config (for unit tests)
                    if (array_key_exists($key, $config)) {
                        return $config[$key];
                    }
                    return $default;
                }
            ');
        }

        \DevToolbelt\LaravelFastCrud\Actions\config($config);
    }
}
