<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use DevToolbelt\LaravelFastCrud\Actions\Read;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class ReadActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class {
            use Read;

            public ?Builder $modifiedQuery = null;
            public ?Model $afterReadRecord = null;
            public bool $answerInvalidUuidCalled = false;
            public ?HttpStatusCode $invalidUuidStatusCode = null;
            public bool $answerRecordNotFoundCalled = false;
            public bool $answerSuccessCalled = false;
            public string $modelClass = '';

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifyReadQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function afterRead(Model $record): void
            {
                $this->afterReadRecord = $record;
            }

            protected function answerInvalidUuid(
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                $this->answerInvalidUuidCalled = true;
                $this->invalidUuidStatusCode = $code;
                return new JsonResponse(['status' => 'fail', 'message' => 'Invalid UUID'], $code->value);
            }

            protected function answerRecordNotFound(): JsonResponse|ResponseInterface
            {
                $this->answerRecordNotFoundCalled = true;
                return new JsonResponse(['status' => 'fail', 'message' => 'Not found'], 404);
            }

            protected function answerSuccess(
                mixed $data,
                HttpStatusCode $code = HttpStatusCode::OK,
                array $meta = []
            ): JsonResponse|ResponseInterface {
                $this->answerSuccessCalled = true;
                return new JsonResponse(['status' => 'success', 'data' => $data], $code->value);
            }
        };
    }

    public function testReadReturnsRecordNotFoundWhenNoRecord(): void
    {
        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn(null);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => null,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => false,
            'devToolbelt.fast-crud.read.method' => 'toArray',
        ]);

        $this->controller->read('999');

        $this->assertTrue($this->controller->answerRecordNotFoundCalled);
    }

    public function testReadReturnsInvalidUuidWhenUuidValidationFails(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => true,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => true,
            'devToolbelt.fast-crud.read.method' => 'toArray',
        ]);

        $this->controller->read('not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
    }

    public function testModifyReadQueryHookIsCalled(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => null,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => false,
            'devToolbelt.fast-crud.read.method' => 'toArray',
        ]);

        $this->controller->read('1');

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testAfterReadHookIsCalled(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => null,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => false,
            'devToolbelt.fast-crud.read.method' => 'toArray',
        ]);

        $this->controller->read('1');

        $this->assertSame($modelMock, $this->controller->afterReadRecord);
    }

    public function testReadReturnsSuccessWithRecord(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test']);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => null,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => false,
            'devToolbelt.fast-crud.read.method' => 'toArray',
        ]);

        $response = $this->controller->read('1');

        $this->assertTrue($this->controller->answerSuccessCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testReadUsesHttpStatusFromConfig(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test']);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => null,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => false,
            'devToolbelt.fast-crud.read.method' => 'toArray',
            'devToolbelt.fast-crud.read.http_status' => HttpStatusCode::ACCEPTED->value,
        ]);

        $response = $this->controller->read('1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(HttpStatusCode::ACCEPTED->value, $response->getStatusCode());
    }

    public function testReadUsesDefaultHttpStatusWhenNotConfigured(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test']);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => null,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => false,
            'devToolbelt.fast-crud.read.method' => 'toArray',
        ]);

        $response = $this->controller->read('1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(HttpStatusCode::OK->value, $response->getStatusCode());
    }

    public function testReadInvalidUuidUsesValidationHttpStatusFromConfig(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => true,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => true,
            'devToolbelt.fast-crud.read.method' => 'toArray',
            'devToolbelt.fast-crud.global.validation.http_status' => HttpStatusCode::UNPROCESSABLE_ENTITY->value,
        ]);

        $response = $this->controller->read('not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
        $this->assertSame(HttpStatusCode::UNPROCESSABLE_ENTITY, $this->controller->invalidUuidStatusCode);
        $this->assertSame(HttpStatusCode::UNPROCESSABLE_ENTITY->value, $response->getStatusCode());
    }

    public function testReadInvalidUuidUsesDefaultValidationHttpStatus(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.read.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.read.find_field_is_uuid' => true,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => true,
            'devToolbelt.fast-crud.read.method' => 'toArray',
        ]);

        $response = $this->controller->read('not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
        $this->assertSame(HttpStatusCode::BAD_REQUEST, $this->controller->invalidUuidStatusCode);
        $this->assertSame(HttpStatusCode::BAD_REQUEST->value, $response->getStatusCode());
    }

    private function createMockModelClass(Builder $builderMock): string
    {
        $className = 'TestReadModel' . uniqid();

        eval("
            namespace DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions;

            class {$className} extends \\Illuminate\\Database\\Eloquent\\Model {
                public static \$builderMock;

                public static function query() {
                    return self::\$builderMock;
                }
            }

            {$className}::\$builderMock = \$builderMock;
        ");

        $fullClassName = "DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions\\{$className}";
        $fullClassName::$builderMock = $builderMock;

        return $fullClassName;
    }

    private function mockConfig(array $config): void
    {
        // Mock the config helper
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

        // Set config values
        \DevToolbelt\LaravelFastCrud\Actions\config($config);
    }
}
