<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use DevToolbelt\LaravelFastCrud\Actions\Delete;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class DeleteActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class {
            use Delete;

            public ?Builder $modifiedQuery = null;
            public ?Model $beforeDeleteRecord = null;
            public ?Model $afterDeleteRecord = null;
            public bool $answerInvalidUuidCalled = false;
            public bool $answerRecordNotFoundCalled = false;
            public bool $answerNoContentCalled = false;
            public string $modelClass = '';

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifyDeleteQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function beforeDelete(Model $record): void
            {
                $this->beforeDeleteRecord = $record;
            }

            protected function afterDelete(Model $record): void
            {
                $this->afterDeleteRecord = $record;
            }

            protected function answerInvalidUuid(): JsonResponse|ResponseInterface
            {
                $this->answerInvalidUuidCalled = true;
                return new JsonResponse(['status' => 'fail'], 400);
            }

            protected function answerRecordNotFound(): JsonResponse|ResponseInterface
            {
                $this->answerRecordNotFoundCalled = true;
                return new JsonResponse(['status' => 'fail'], 404);
            }

            protected function answerNoContent(
                HttpStatusCode $code = HttpStatusCode::OK
            ): JsonResponse|ResponseInterface {
                $this->answerNoContentCalled = true;
                return new JsonResponse(null, $code->value);
            }
        };
    }

    public function testDeleteReturnsInvalidUuidWhenUuidValidationFails(): void
    {
        $this->mockConfig(['devToolbelt.fast-crud.delete.find_field_is_uuid' => true]);

        $this->controller->delete('not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
    }

    public function testDeleteReturnsRecordNotFoundWhenNoRecord(): void
    {
        $this->mockConfig();

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn(null);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->delete('999');

        $this->assertTrue($this->controller->answerRecordNotFoundCalled);
    }

    public function testModifyDeleteQueryHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('delete')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->delete('1');

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testBeforeDeleteHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('delete')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->delete('1');

        $this->assertSame($modelMock, $this->controller->beforeDeleteRecord);
    }

    public function testAfterDeleteHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('delete')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->delete('1');

        $this->assertSame($modelMock, $this->controller->afterDeleteRecord);
    }

    public function testDeleteReturnsNoContentResponse(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('delete')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->delete('1');

        $this->assertTrue($this->controller->answerNoContentCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteCallsModelDeleteMethod(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('delete')->once()->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->delete('1');

        $this->addToAssertionCount(1);
    }

    public function testDeleteUsesHttpStatusFromConfig(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.delete.http_status' => HttpStatusCode::NO_CONTENT->value,
        ]);

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('delete')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->delete('1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(HttpStatusCode::NO_CONTENT->value, $response->getStatusCode());
    }

    public function testDeleteUsesDefaultHttpStatusWhenNotConfigured(): void
    {
        $this->mockConfig([]);

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('delete')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->delete('1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(HttpStatusCode::OK->value, $response->getStatusCode());
    }

    private function createMockModelClass(Builder $builderMock): string
    {
        $className = 'TestDeleteModel' . uniqid();

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
            'devToolbelt.fast-crud.delete.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.delete.find_field_is_uuid' => null,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => false,
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
