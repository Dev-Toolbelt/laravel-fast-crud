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
    private object $controllerWithValidation;

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

            protected function runValidation(array $data, array $rules): JsonResponse|ResponseInterface|null
            {
                return null;
            }
        };

        $this->controllerWithValidation = new class {
            use Update;

            public ?Builder $modifiedQuery = null;
            public array $validationData = [];
            public array $validationRules = [];
            public bool $answerInvalidUuidCalled = false;
            public bool $answerEmptyPayloadCalled = false;
            public bool $answerRecordNotFoundCalled = false;
            public bool $answerSuccessCalled = false;
            public bool $shouldFailValidation = false;
            public array $failData = [];
            public string $modelClass = '';

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            public function setShouldFailValidation(bool $shouldFail): void
            {
                $this->shouldFailValidation = $shouldFail;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function updateValidateRules(): array
            {
                return [
                    'name' => ['sometimes', 'string', 'max:255'],
                    'email' => ['sometimes', 'email'],
                    'price' => ['sometimes', 'numeric', 'min:0'],
                ];
            }

            protected function modifyUpdateQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function beforeUpdateFill(array &$data): void
            {
                $data['updated_by_hook'] = true;
            }

            protected function beforeUpdate(Model $record, array &$data): void
            {
            }

            protected function afterUpdate(Model $record): void
            {
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

            protected function runValidation(array $data, array $rules): JsonResponse|ResponseInterface|null
            {
                $this->validationData = $data;
                $this->validationRules = $rules;

                if (empty($rules)) {
                    return null;
                }

                if ($this->shouldFailValidation) {
                    $this->failData = [
                        [
                            'field' => 'email',
                            'error' => 'email',
                            'value' => $data['email'] ?? null,
                            'message' => 'The email field must be a valid email address.',
                        ],
                    ];
                    return new JsonResponse(['status' => 'fail', 'data' => $this->failData], 400);
                }

                return null;
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

    public function testUpdateValidationIsCalledWithRules(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controllerWithValidation->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Updated Name']);

        $this->controllerWithValidation->update($request, '1');

        $this->assertNotEmpty($this->controllerWithValidation->validationRules);
        $this->assertArrayHasKey('name', $this->controllerWithValidation->validationRules);
        $this->assertArrayHasKey('email', $this->controllerWithValidation->validationRules);
        $this->assertArrayHasKey('price', $this->controllerWithValidation->validationRules);
    }

    public function testUpdateValidationReceivesDataAfterBeforeUpdateFill(): void
    {
        $this->mockConfig();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controllerWithValidation->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn(['name' => 'Test']);

        $this->controllerWithValidation->update($request, '1');

        // Data should include field added by beforeUpdateFill
        $this->assertArrayHasKey('updated_by_hook', $this->controllerWithValidation->validationData);
        $this->assertTrue($this->controllerWithValidation->validationData['updated_by_hook']);
    }

    public function testUpdateReturnsValidationErrorWhenValidationFails(): void
    {
        $this->mockConfig();
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'email' => 'invalid-email',
        ]);

        $response = $this->controllerWithValidation->update($request, '1');

        $this->assertNotEmpty($this->controllerWithValidation->failData);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testUpdateValidationErrorContainsCorrectFields(): void
    {
        $this->mockConfig();
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'email' => 'invalid-email',
        ]);

        $this->controllerWithValidation->update($request, '1');

        $this->assertNotEmpty($this->controllerWithValidation->failData);
        $this->assertSame('email', $this->controllerWithValidation->failData[0]['field']);
        $this->assertSame('email', $this->controllerWithValidation->failData[0]['error']);
    }

    public function testUpdateValidationErrorContainsSubmittedValue(): void
    {
        $this->mockConfig();
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'email' => 'not-valid-email',
        ]);

        $this->controllerWithValidation->update($request, '1');

        $this->assertSame('email', $this->controllerWithValidation->failData[0]['field']);
        $this->assertSame('not-valid-email', $this->controllerWithValidation->failData[0]['value']);
    }

    public function testUpdateDoesNotProceedWhenValidationFails(): void
    {
        $this->mockConfig();
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'email' => 'invalid',
        ]);

        $this->controllerWithValidation->update($request, '1');

        $this->assertNotEmpty($this->controllerWithValidation->failData);
        $this->assertFalse($this->controllerWithValidation->answerSuccessCalled);
        $this->assertNull($this->controllerWithValidation->modifiedQuery);
    }

    public function testUpdateProceedsWhenValidationPasses(): void
    {
        $this->mockConfig();
        $this->controllerWithValidation->setShouldFailValidation(false);

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controllerWithValidation->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'price' => 99.99,
        ]);

        $this->controllerWithValidation->update($request, '1');

        $this->assertEmpty($this->controllerWithValidation->failData);
        $this->assertTrue($this->controllerWithValidation->answerSuccessCalled);
    }

    public function testUpdateValidationWithSometimesRuleSkipsMissingFields(): void
    {
        $this->mockConfig();
        $this->controllerWithValidation->setShouldFailValidation(false);

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('update')->andReturn(true);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('first')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controllerWithValidation->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => 'Only updating name', // email and price not sent
        ]);

        $this->controllerWithValidation->update($request, '1');

        // Should pass because validation does not fail
        $this->assertEmpty($this->controllerWithValidation->failData);
        $this->assertTrue($this->controllerWithValidation->answerSuccessCalled);
    }

    public function testUpdateValidationFailsBeforeQueryingDatabase(): void
    {
        $this->mockConfig();
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'email' => 'invalid-email',
        ]);

        $this->controllerWithValidation->update($request, '1');

        // modifyUpdateQuery should not be called because validation failed first
        $this->assertNull($this->controllerWithValidation->modifiedQuery);
        $this->assertFalse($this->controllerWithValidation->answerRecordNotFoundCalled);
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
