<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use DevToolbelt\LaravelFastCrud\Actions\Create;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class CreateActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfig();

        $this->controller = new class {
            use Create;

            public array $beforeCreateFillData = [];
            public array $beforeCreateData = [];
            public ?Model $afterCreateRecord = null;
            public bool $answerEmptyPayloadCalled = false;
            public bool $answerSuccessCalled = false;
            public array $successData = [];
            public string $modelClass = '';

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function beforeCreateFill(array &$data): void
            {
                $this->beforeCreateFillData = $data;
                $data['added_in_before_fill'] = true;
            }

            protected function beforeCreate(array &$data): void
            {
                $this->beforeCreateData = $data;
                $data['added_in_before_create'] = true;
            }

            protected function afterCreate(Model $record): void
            {
                $this->afterCreateRecord = $record;
            }

            protected function answerEmptyPayload(): JsonResponse|ResponseInterface
            {
                $this->answerEmptyPayloadCalled = true;
                return new JsonResponse(['status' => 'fail'], 400);
            }

            protected function answerSuccess(
                array $data,
                array $meta = [],
                mixed $code = null
            ): JsonResponse|ResponseInterface {
                $this->answerSuccessCalled = true;
                $this->successData = $data;
                $statusCode = $code instanceof HttpStatusCode ? $code->value : ($code ?? 200);
                return new JsonResponse(['status' => 'success', 'data' => $data], $statusCode);
            }
        };
    }

    public function testCreateReturnsEmptyPayloadWhenNoData(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([]);

        $this->controller->create($request);

        $this->assertTrue($this->controller->answerEmptyPayloadCalled);
        $this->assertFalse($this->controller->answerSuccessCalled);
    }

    public function testBeforeCreateFillHookIsCalled(): void
    {
        $this->setupModelMock();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->create($request);

        $this->assertArrayHasKey('name', $this->controller->beforeCreateFillData);
        $this->assertSame('Test', $this->controller->beforeCreateFillData['name']);
    }

    public function testBeforeCreateFillCanModifyData(): void
    {
        $this->setupModelMock();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->create($request);

        $this->assertArrayHasKey('added_in_before_fill', $this->controller->beforeCreateData);
        $this->assertTrue($this->controller->beforeCreateData['added_in_before_fill']);
    }

    public function testBeforeCreateHookIsCalled(): void
    {
        $this->setupModelMock();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->create($request);

        $this->assertNotEmpty($this->controller->beforeCreateData);
    }

    public function testAfterCreateHookIsCalled(): void
    {
        $modelMock = $this->setupModelMock();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->create($request);

        $this->assertSame($modelMock, $this->controller->afterCreateRecord);
    }

    public function testCreateReturnsSuccessResponse(): void
    {
        $this->setupModelMock();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $response = $this->controller->create($request);

        $this->assertTrue($this->controller->answerSuccessCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testCreateUsesConfigMethod(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test']);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('create')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->create($request);

        $this->assertArrayHasKey('id', $this->controller->successData);
        $this->assertArrayHasKey('name', $this->controller->successData);
    }

    private function setupModelMock(): Model
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test']);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('create')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        return $modelMock;
    }

    private function createMockModelClass(Builder $builderMock): string
    {
        $className = 'TestCreateModel' . uniqid();

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
            'devToolbelt.fast_crud.create.method' => 'toArray',
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
