<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\LaravelFastCrud\Actions\Restore;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class RestoreActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class {
            use Restore;

            public ?Builder $modifiedQuery = null;
            public ?Model $beforeRestoreRecord = null;
            public ?Model $afterRestoreRecord = null;
            public bool $answerInvalidUuidCalled = false;
            public bool $answerRecordNotFoundCalled = false;
            public bool $answerColumnNotFoundCalled = false;
            public string $columnNotFoundField = '';
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

            protected function modifyRestoreQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function beforeRestore(Model $record): void
            {
                $this->beforeRestoreRecord = $record;
            }

            protected function afterRestore(Model $record): void
            {
                $this->afterRestoreRecord = $record;
            }

            public function hasModelAttribute(Model $model, string $attributeName): bool
            {
                return in_array($attributeName, ['deleted_at', 'deleted_by']);
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

            protected function answerColumnNotFound(string $field): JsonResponse|ResponseInterface
            {
                $this->answerColumnNotFoundCalled = true;
                $this->columnNotFoundField = $field;
                return new JsonResponse(['status' => 'fail'], 400);
            }

            protected function answerSuccess(array $data, array $meta = []): JsonResponse|ResponseInterface
            {
                $this->answerSuccessCalled = true;
                return new JsonResponse(['status' => 'success', 'data' => $data]);
            }
        };
    }

    public function testRestoreReturnsInvalidUuidWhenUuidValidationFails(): void
    {
        $this->mockConfig(['devToolbelt.fast_crud.restore.find_field_is_uuid' => true]);

        $this->controller->restore('not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
    }

    public function testRestoreReturnsRecordNotFoundWhenNoRecord(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNotNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn(null);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->restore('999');

        $this->assertTrue($this->controller->answerRecordNotFoundCalled);
    }

    public function testModifyRestoreQueryHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNotNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->restore('1');

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testBeforeRestoreHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNotNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->restore('1');

        $this->assertSame($modelMock, $this->controller->beforeRestoreRecord);
    }

    public function testAfterRestoreHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNotNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->restore('1');

        $this->assertSame($modelMock, $this->controller->afterRestoreRecord);
    }

    public function testRestoreReturnsSuccessResponse(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNotNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->restore('1');

        $this->assertTrue($this->controller->answerSuccessCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testRestoreCallsUpdateWithCorrectFields(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['deleted_at'] === null
                    && $data['deleted_by'] === null;
            }))
            ->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNotNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->restore('1');

        $this->addToAssertionCount(1);
    }

    private function createModelMockWithAttributes(): Model
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn(['deleted_at', 'deleted_by']);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        return $modelMock;
    }

    private function createMockModelClass(Builder $builderMock, Model $modelMock): string
    {
        $className = 'TestRestoreModel' . uniqid();

        eval("
            namespace DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions;

            class {$className} extends \\Illuminate\\Database\\Eloquent\\Model {
                public static \$builderMock;
                public static \$modelInstance;

                public static function query() {
                    return self::\$builderMock;
                }

                public function __construct() {
                    // Don't call parent
                }

                public function getFillable() {
                    return ['deleted_at', 'deleted_by'];
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

        $fullClassName = "DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions\\{$className}";
        $fullClassName::$builderMock = $builderMock;
        $fullClassName::$modelInstance = $modelMock;

        return $fullClassName;
    }

    private function mockConfig(array $overrides = []): void
    {
        $defaults = [
            'devToolbelt.fast_crud.restore.find_field' => null,
            'devToolbelt.fast_crud.global.find_field' => 'id',
            'devToolbelt.fast_crud.restore.find_field_is_uuid' => null,
            'devToolbelt.fast_crud.global.find_field_is_uuid' => false,
            'devToolbelt.fast_crud.restore.method' => 'toArray',
            'devToolbelt.fast_crud.soft_delete.deleted_at_field' => 'deleted_at',
            'devToolbelt.fast_crud.soft_delete.deleted_by_field' => 'deleted_by',
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
