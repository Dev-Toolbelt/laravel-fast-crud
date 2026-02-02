<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\LaravelFastCrud\Actions\Update;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class UpdateActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class {
            use Update;

            public ?Builder $modifiedQuery = null;
            public array $beforeUpdateFillData = [];
            public ?Model $beforeUpdateRecord = null;
            public array $beforeUpdateData = [];
            public ?Model $afterUpdateRecord = null;
            public bool $answerInvalidUuidCalled = false;
            public bool $answerEmptyPayloadCalled = false;
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

            protected function modifyUpdateQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function beforeUpdateFill(array &$data): void
            {
                $this->beforeUpdateFillData = $data;
                $data['modified_by_hook'] = true;
            }

            protected function beforeUpdate(Model $record, array &$data): void
            {
                $this->beforeUpdateRecord = $record;
                $this->beforeUpdateData = $data;
            }

            protected function afterUpdate(Model $record): void
            {
                $this->afterUpdateRecord = $record;
            }

            protected function answerInvalidUuid(): JsonResponse|ResponseInterface
            {
                $this->answerInvalidUuidCalled = true;
                return new JsonResponse(['status' => 'fail'], 400);
            }

            protected function answerEmptyPayload(): JsonResponse|ResponseInterface
            {
                $this->answerEmptyPayloadCalled = true;
                return new JsonResponse(['status' => 'fail'], 400);
            }

            protected function answerRecordNotFound(): JsonResponse|ResponseInterface
            {
                $this->answerRecordNotFoundCalled = true;
                return new JsonResponse(['status' => 'fail'], 404);
            }

            protected function answerSuccess(array $data, array $meta = []): JsonResponse|ResponseInterface
            {
                $this->answerSuccessCalled = true;
                return new JsonResponse(['status' => 'success', 'data' => $data]);
            }
        };
    }

    public function testUpdateReturnsEmptyPayloadWhenNoData(): void
    {
        $this->mockConfig();

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([]);

        $this->controller->update($request, '1');

        $this->assertTrue($this->controller->answerEmptyPayloadCalled);
    }

    public function testUpdateReturnsInvalidUuidWhenUuidValidationFails(): void
    {
        $this->mockConfig(['devToolbelt.fast_crud.update.find_field_is_uuid' => true]);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->update($request, 'not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
    }

    public function testUpdateReturnsRecordNotFoundWhenNoRecord(): void
    {
        $this->mockConfig();

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn(null);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->update($request, '999');

        $this->assertTrue($this->controller->answerRecordNotFoundCalled);
    }

    public function testModifyUpdateQueryHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Updated']);

        $this->controller->update($request, '1');

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testBeforeUpdateFillHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->update($request, '1');

        $this->assertArrayHasKey('name', $this->controller->beforeUpdateFillData);
    }

    public function testBeforeUpdateFillCanModifyData(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->update($request, '1');

        $this->assertArrayHasKey('modified_by_hook', $this->controller->beforeUpdateData);
        $this->assertTrue($this->controller->beforeUpdateData['modified_by_hook']);
    }

    public function testBeforeUpdateHookReceivesRecordAndData(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->update($request, '1');

        $this->assertSame($modelMock, $this->controller->beforeUpdateRecord);
        $this->assertNotEmpty($this->controller->beforeUpdateData);
    }

    public function testAfterUpdateHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controller->update($request, '1');

        $this->assertSame($modelMock, $this->controller->afterUpdateRecord);
    }

    public function testUpdateReturnsSuccessResponse(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Updated']);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Updated']);

        $response = $this->controller->update($request, '1');

        $this->assertTrue($this->controller->answerSuccessCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    private function createMockModelClass(Builder $builderMock): string
    {
        $className = 'TestUpdateModel' . uniqid();

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
            'devToolbelt.fast_crud.update.method' => 'toArray',
            'devToolbelt.fast_crud.update.find_field' => null,
            'devToolbelt.fast_crud.global.find_field' => 'id',
            'devToolbelt.fast_crud.update.find_field_is_uuid' => null,
            'devToolbelt.fast_crud.global.find_field_is_uuid' => false,
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
