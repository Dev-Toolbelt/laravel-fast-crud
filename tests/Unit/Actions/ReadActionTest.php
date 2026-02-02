<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

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

            protected function answerInvalidUuid(): JsonResponse|ResponseInterface
            {
                $this->answerInvalidUuidCalled = true;
                return new JsonResponse(['status' => 'fail', 'message' => 'Invalid UUID'], 400);
            }

            protected function answerRecordNotFound(): JsonResponse|ResponseInterface
            {
                $this->answerRecordNotFoundCalled = true;
                return new JsonResponse(['status' => 'fail', 'message' => 'Not found'], 404);
            }

            protected function answerSuccess(array $data, array $meta = []): JsonResponse|ResponseInterface
            {
                $this->answerSuccessCalled = true;
                return new JsonResponse(['status' => 'success', 'data' => $data]);
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
                        $config = array_merge($config, $key);
                        return null;
                    }
                    return $config[$key] ?? $default;
                }
            ');
        }

        // Set config values
        \DevToolbelt\LaravelFastCrud\Actions\config($config);
    }
}
