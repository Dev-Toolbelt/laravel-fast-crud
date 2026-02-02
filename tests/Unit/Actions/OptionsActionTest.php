<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\LaravelFastCrud\Actions\Options;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use Psr\Http\Message\ResponseInterface;

final class OptionsActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class {
            use Options;

            public ?Builder $modifiedQuery = null;
            public array $afterOptionsData = [];
            public bool $answerRequiredCalled = false;
            public string $requiredField = '';
            public bool $answerColumnNotFoundCalled = false;
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

            protected function modifyOptionsQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            protected function afterOptions(array $rows): void
            {
                $this->afterOptionsData = $rows;
            }

            public function hasModelAttribute(Model $model, string $attributeName): bool
            {
                return in_array($attributeName, ['name', 'title', 'external_id', 'id']);
            }

            protected function answerRequired(string $field): JsonResponse|ResponseInterface
            {
                $this->answerRequiredCalled = true;
                $this->requiredField = $field;
                return new JsonResponse(['status' => 'fail', 'message' => "$field is required"], 400);
            }

            protected function answerColumnNotFound(string $field): JsonResponse|ResponseInterface
            {
                $this->answerColumnNotFoundCalled = true;
                $this->columnNotFoundField = $field;
                return new JsonResponse(['status' => 'fail', 'message' => "Column $field not found"], 400);
            }

            protected function answerSuccess(array $data, array $meta = []): JsonResponse|ResponseInterface
            {
                $this->answerSuccessCalled = true;
                $this->responseData = $data;
                return new JsonResponse(['status' => 'success', 'data' => $data]);
            }
        };
    }

    public function testOptionsReturnsRequiredWhenLabelMissing(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'external_id')->andReturn('external_id');
        $request->shouldReceive('get')->with('label')->andReturn(null);

        $this->controller->options($request);

        $this->assertTrue($this->controller->answerRequiredCalled);
        $this->assertSame('label', $this->controller->requiredField);
    }

    public function testOptionsReturnsColumnNotFoundForInvalidLabel(): void
    {
        $modelMock = $this->createModelMock();
        $modelClass = $this->createMockModelClass($modelMock);
        $this->controller->setModelClass($modelClass);

        // Override hasModelAttribute to return false for invalid column
        $this->controller = new class {
            use Options;

            public ?Builder $modifiedQuery = null;
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

            protected function modifyOptionsQuery(Builder $query): void
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

        $modelClass = $this->createMockModelClass($modelMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'external_id')->andReturn('external_id');
        $request->shouldReceive('get')->with('label')->andReturn('invalid_column');

        $this->controller->options($request);

        $this->assertTrue($this->controller->answerColumnNotFoundCalled);
        $this->assertSame('label', $this->controller->columnNotFoundField);
    }

    public function testModifyOptionsQueryHookIsCalled(): void
    {
        $modelMock = $this->createModelMock();
        $modelClass = $this->createMockModelClass($modelMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'external_id')->andReturn('external_id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testAfterOptionsHookIsCalled(): void
    {
        $modelMock = $this->createModelMock();
        $modelClass = $this->createMockModelClass($modelMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'external_id')->andReturn('external_id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertIsArray($this->controller->afterOptionsData);
    }

    public function testOptionsReturnsSuccessResponse(): void
    {
        $modelMock = $this->createModelMock();
        $modelClass = $this->createMockModelClass($modelMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'external_id')->andReturn('external_id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $response = $this->controller->options($request);

        $this->assertTrue($this->controller->answerSuccessCalled);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testOptionsReturnsFormattedLabelValuePairs(): void
    {
        $modelMock = $this->createModelMock();
        $modelClass = $this->createMockModelClass($modelMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('value', 'external_id')->andReturn('id');
        $request->shouldReceive('get')->with('label')->andReturn('name');

        $this->controller->options($request);

        $this->assertNotEmpty($this->controller->responseData);
        $this->assertArrayHasKey('label', $this->controller->responseData[0]);
        $this->assertArrayHasKey('value', $this->controller->responseData[0]);
    }

    private function createModelMock(): Model
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn(['name', 'title']);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('select')->andReturnSelf();
        $builderMock->shouldReceive('orderBy')->andReturnSelf();
        $builderMock->shouldReceive('get')->andReturn(collect([
            ['label' => 'Option 1', 'value' => '1'],
            ['label' => 'Option 2', 'value' => '2'],
        ]));

        $modelMock->shouldReceive('newQuery')->andReturn($builderMock);

        return $modelMock;
    }

    private function createMockModelClass(Model $modelMock): string
    {
        $className = 'TestOptionsModel' . uniqid();

        eval("
            namespace DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions;

            class {$className} extends \\Illuminate\\Database\\Eloquent\\Model {
                public static \$modelInstance;

                public function __construct() {
                    // Don't call parent
                }

                public function newQuery() {
                    \$builderMock = \\Mockery::mock(\\Illuminate\\Database\\Eloquent\\Builder::class);
                    \$builderMock->shouldReceive('select')->andReturnSelf();
                    \$builderMock->shouldReceive('orderBy')->andReturnSelf();
                    \$builderMock->shouldReceive('get')->andReturn(collect([
                        ['label' => 'Option 1', 'value' => '1'],
                        ['label' => 'Option 2', 'value' => '2'],
                    ]));
                    return \$builderMock;
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

        $fullClassName = "DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions\\{$className}";
        $fullClassName::$modelInstance = $modelMock;

        return $fullClassName;
    }
}
