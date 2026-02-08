<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use DevToolbelt\LaravelFastCrud\Actions\SoftDelete;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class SoftDeleteActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class {
            use SoftDelete;

            public ?Builder $modifiedQuery = null;
            public ?Model $beforeSoftDeleteRecord = null;
            public ?Model $afterSoftDeleteRecord = null;
            public bool $answerInvalidUuidCalled = false;
            public ?HttpStatusCode $invalidUuidStatusCode = null;
            public bool $answerRecordNotFoundCalled = false;
            public bool $answerColumnNotFoundCalled = false;
            public ?HttpStatusCode $columnNotFoundStatusCode = null;
            public string $columnNotFoundField = '';
            public bool $answerNoContentCalled = false;
            public string $modelClass = '';
            public int|string|null $softDeleteUserId = 1;

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifySoftDeleteQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function beforeSoftDelete(Model $record): void
            {
                $this->beforeSoftDeleteRecord = $record;
            }

            protected function afterSoftDelete(Model $record): void
            {
                $this->afterSoftDeleteRecord = $record;
            }

            protected function getSoftDeleteUserId(): int|string|null
            {
                return $this->softDeleteUserId;
            }

            public function hasModelAttribute(Model $model, string $attributeName): bool
            {
                return in_array($attributeName, ['deleted_at', 'deleted_by']);
            }

            protected function answerInvalidUuid(
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                $this->answerInvalidUuidCalled = true;
                $this->invalidUuidStatusCode = $code;
                return new JsonResponse(['status' => 'fail'], $code->value);
            }

            protected function answerRecordNotFound(): JsonResponse|ResponseInterface
            {
                $this->answerRecordNotFoundCalled = true;
                return new JsonResponse(['status' => 'fail'], 404);
            }

            protected function answerColumnNotFound(
                string $field,
                HttpStatusCode $code = HttpStatusCode::BAD_REQUEST
            ): JsonResponse|ResponseInterface {
                $this->answerColumnNotFoundCalled = true;
                $this->columnNotFoundField = $field;
                $this->columnNotFoundStatusCode = $code;
                return new JsonResponse(['status' => 'fail'], $code->value);
            }

            protected function answerNoContent(
                HttpStatusCode $code = HttpStatusCode::OK
            ): JsonResponse|ResponseInterface {
                $this->answerNoContentCalled = true;
                return new JsonResponse(null, $code->value);
            }
        };
    }

    public function testSoftDeleteReturnsInvalidUuidWhenUuidValidationFails(): void
    {
        $this->mockConfig(['devToolbelt.fast-crud.soft_delete.find_field_is_uuid' => true]);

        $this->controller->softDelete('not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
    }

    public function testSoftDeleteReturnsRecordNotFoundWhenNoRecord(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn(null);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->softDelete('999');

        $this->assertTrue($this->controller->answerRecordNotFoundCalled);
    }

    public function testSoftDeleteReturnsColumnNotFoundWhenDeletedAtIsMissing(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.soft_delete.deleted_at_field' => 'deleted_at_missing',
        ]);

        $modelMock = $this->createModelMockWithAttributes();
        $builderMock = Mockery::mock(Builder::class);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->softDelete('1');

        $this->assertTrue($this->controller->answerColumnNotFoundCalled);
        $this->assertSame('deleted_at_missing', $this->controller->columnNotFoundField);
    }

    public function testSoftDeleteReturnsColumnNotFoundWhenDeletedByIsMissing(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.soft_delete.deleted_by_field' => 'deleted_by_missing',
        ]);

        $modelMock = $this->createModelMockWithAttributes();
        $builderMock = Mockery::mock(Builder::class);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->softDelete('1');

        $this->assertTrue($this->controller->answerColumnNotFoundCalled);
        $this->assertSame('deleted_by_missing', $this->controller->columnNotFoundField);
    }

    public function testModifySoftDeleteQueryHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->softDelete('1');

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testBeforeSoftDeleteHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->softDelete('1');

        $this->assertSame($modelMock, $this->controller->beforeSoftDeleteRecord);
    }

    public function testAfterSoftDeleteHookIsCalled(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->softDelete('1');

        $this->assertSame($modelMock, $this->controller->afterSoftDeleteRecord);
    }

    public function testSoftDeleteReturnsNoContentResponse(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->softDelete('1');

        $this->assertTrue($this->controller->answerNoContentCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSoftDeleteCallsUpdateWithCorrectFields(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($data) {
                return isset($data['deleted_at'])
                    && isset($data['deleted_by'])
                    && $data['deleted_by'] === 1;
            }))
            ->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->softDelete('1');

        $this->addToAssertionCount(1);
    }

    public function testSoftDeleteDoesNotCallLaravelDeleteMethod(): void
    {
        $this->mockConfig();

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldNotReceive('delete');

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $this->controller->softDelete('1');

        $this->addToAssertionCount(1);
    }

    public function testGetSoftDeleteUserIdReturnsConfiguredValue(): void
    {
        $this->controller->softDeleteUserId = 42;

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getSoftDeleteUserId');
        $method->setAccessible(true);

        $this->assertSame(42, $method->invoke($this->controller));
    }

    public function testSoftDeleteUsesHttpStatusFromConfig(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.soft_delete.http_status' => HttpStatusCode::NO_CONTENT->value,
        ]);

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->softDelete('1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(HttpStatusCode::NO_CONTENT->value, $response->getStatusCode());
    }

    public function testSoftDeleteUsesDefaultHttpStatusWhenNotConfigured(): void
    {
        $this->mockConfig([]);

        $modelMock = $this->createModelMockWithAttributes();
        $modelMock->shouldReceive('update')->andReturn(true);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereNull')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->softDelete('1');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(HttpStatusCode::OK->value, $response->getStatusCode());
    }

    public function testSoftDeleteInvalidUuidUsesValidationHttpStatusFromConfig(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.soft_delete.find_field_is_uuid' => true,
            'devToolbelt.fast-crud.global.validation.http_status' => HttpStatusCode::UNPROCESSABLE_ENTITY->value,
        ]);

        $response = $this->controller->softDelete('not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
        $this->assertSame(HttpStatusCode::UNPROCESSABLE_ENTITY, $this->controller->invalidUuidStatusCode);
        $this->assertSame(HttpStatusCode::UNPROCESSABLE_ENTITY->value, $response->getStatusCode());
    }

    public function testSoftDeleteInvalidUuidUsesDefaultValidationHttpStatus(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.soft_delete.find_field_is_uuid' => true,
        ]);

        $response = $this->controller->softDelete('not-a-uuid');

        $this->assertTrue($this->controller->answerInvalidUuidCalled);
        $this->assertSame(HttpStatusCode::BAD_REQUEST, $this->controller->invalidUuidStatusCode);
        $this->assertSame(HttpStatusCode::BAD_REQUEST->value, $response->getStatusCode());
    }

    public function testSoftDeleteColumnNotFoundUsesValidationHttpStatusFromConfig(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.soft_delete.deleted_at_field' => 'deleted_at_missing',
            'devToolbelt.fast-crud.global.validation.http_status' => HttpStatusCode::UNPROCESSABLE_ENTITY->value,
        ]);

        $modelMock = $this->createModelMockWithAttributes();
        $builderMock = Mockery::mock(Builder::class);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->softDelete('1');

        $this->assertTrue($this->controller->answerColumnNotFoundCalled);
        $this->assertSame(HttpStatusCode::UNPROCESSABLE_ENTITY, $this->controller->columnNotFoundStatusCode);
        $this->assertSame(HttpStatusCode::UNPROCESSABLE_ENTITY->value, $response->getStatusCode());
    }

    public function testSoftDeleteColumnNotFoundUsesDefaultValidationHttpStatus(): void
    {
        $this->mockConfig([
            'devToolbelt.fast-crud.soft_delete.deleted_at_field' => 'deleted_at_missing',
        ]);

        $modelMock = $this->createModelMockWithAttributes();
        $builderMock = Mockery::mock(Builder::class);

        $modelClass = $this->createMockModelClass($builderMock, $modelMock);
        $this->controller->setModelClass($modelClass);

        $response = $this->controller->softDelete('1');

        $this->assertTrue($this->controller->answerColumnNotFoundCalled);
        $this->assertSame(HttpStatusCode::BAD_REQUEST, $this->controller->columnNotFoundStatusCode);
        $this->assertSame(HttpStatusCode::BAD_REQUEST->value, $response->getStatusCode());
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
        $className = 'TestSoftDeleteModel' . uniqid();

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
            'devToolbelt.fast-crud.soft_delete.find_field' => null,
            'devToolbelt.fast-crud.global.find_field' => 'id',
            'devToolbelt.fast-crud.soft_delete.find_field_is_uuid' => null,
            'devToolbelt.fast-crud.global.find_field_is_uuid' => false,
            'devToolbelt.fast-crud.soft_delete.deleted_at_field' => 'deleted_at',
            'devToolbelt.fast-crud.soft_delete.deleted_by_field' => 'deleted_by',
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
