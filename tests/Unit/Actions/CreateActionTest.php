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
    private object $controllerWithValidation;

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

            protected function runValidation(array $data, array $rules): JsonResponse|ResponseInterface|null
            {
                return null;
            }
        };

        $this->controllerWithValidation = new class {
            use Create;

            public array $validationData = [];
            public array $validationRules = [];
            public bool $answerEmptyPayloadCalled = false;
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

            protected function createValidateRules(): array
            {
                return [
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email'],
                ];
            }

            protected function beforeCreateFill(array &$data): void
            {
                $data['added_by_hook'] = true;
            }

            protected function beforeCreate(array &$data): void
            {
            }

            protected function afterCreate(Model $record): void
            {
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
                $statusCode = $code instanceof HttpStatusCode ? $code->value : ($code ?? 200);
                return new JsonResponse(['status' => 'success', 'data' => $data], $statusCode);
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
                            'field' => 'name',
                            'error' => 'required',
                            'value' => $data['name'] ?? null,
                            'message' => 'The name field is required.',
                        ],
                    ];
                    return new JsonResponse(['status' => 'fail', 'data' => $this->failData], 400);
                }

                return null;
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

    public function testCreateValidationIsCalledWithRules(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('create')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controllerWithValidation->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => 'Test Product',
            'email' => 'test@example.com',
        ]);

        $this->controllerWithValidation->create($request);

        $this->assertNotEmpty($this->controllerWithValidation->validationRules);
        $this->assertArrayHasKey('name', $this->controllerWithValidation->validationRules);
        $this->assertArrayHasKey('email', $this->controllerWithValidation->validationRules);
    }

    public function testCreateValidationReceivesDataAfterBeforeCreateFill(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('create')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controllerWithValidation->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        $this->controllerWithValidation->create($request);

        // Data should include field added by beforeCreateFill
        $this->assertArrayHasKey('added_by_hook', $this->controllerWithValidation->validationData);
        $this->assertTrue($this->controllerWithValidation->validationData['added_by_hook']);
    }

    public function testCreateReturnsValidationErrorWhenValidationFails(): void
    {
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => '',
            'email' => 'invalid-email',
        ]);

        $response = $this->controllerWithValidation->create($request);

        $this->assertNotEmpty($this->controllerWithValidation->failData);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateValidationErrorContainsCorrectFields(): void
    {
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => '',
            'email' => 'test@example.com',
        ]);

        $this->controllerWithValidation->create($request);

        $this->assertNotEmpty($this->controllerWithValidation->failData);
        $this->assertSame('name', $this->controllerWithValidation->failData[0]['field']);
        $this->assertSame('required', $this->controllerWithValidation->failData[0]['error']);
    }

    public function testCreateValidationErrorContainsSubmittedValue(): void
    {
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => '',
            'email' => 'test@example.com',
        ]);

        $this->controllerWithValidation->create($request);

        $this->assertSame('name', $this->controllerWithValidation->failData[0]['field']);
        $this->assertSame('', $this->controllerWithValidation->failData[0]['value']);
    }

    public function testCreateDoesNotProceedWhenValidationFails(): void
    {
        $this->controllerWithValidation->setShouldFailValidation(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => '',
            'email' => 'test@example.com',
        ]);

        $this->controllerWithValidation->create($request);

        $this->assertNotEmpty($this->controllerWithValidation->failData);
        $this->assertFalse($this->controllerWithValidation->answerSuccessCalled);
    }

    public function testCreateProceedsWhenValidationPasses(): void
    {
        $this->controllerWithValidation->setShouldFailValidation(false);

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('toArray')->andReturn(['id' => 1]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('create')->andReturn($modelMock);

        $modelClass = $this->createMockModelClass($builderMock);
        $this->controllerWithValidation->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('post')->andReturn([
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
        ]);

        $this->controllerWithValidation->create($request);

        $this->assertEmpty($this->controllerWithValidation->failData);
        $this->assertTrue($this->controllerWithValidation->answerSuccessCalled);
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
