<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Traits;

use DevToolbelt\LaravelFastCrud\Traits\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Validator as ValidatorInstance;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class HelpersTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new class {
            use Helpers;

            public array $failData = [];

            protected function answerFail(array $data): JsonResponse|ResponseInterface
            {
                $this->failData = $data;
                return new JsonResponse(['status' => 'fail', 'data' => $data], 400);
            }

            public function callRunValidation(array $data, array $rules): JsonResponse|ResponseInterface|null
            {
                return $this->runValidation($data, $rules);
            }

            public function callHasModelAttribute(Model $model, string $attributeName): bool
            {
                return $this->hasModelAttribute($model, $attributeName);
            }
        };
    }

    // =========================================================================
    // hasModelAttribute Tests
    // =========================================================================

    public function testHasModelAttributeReturnsTrueForFillableAttribute(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn(['name', 'email']);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $this->controller->callHasModelAttribute($modelMock, 'name');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsTrueForGuardedAttribute(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn(['id', 'created_at']);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $this->controller->callHasModelAttribute($modelMock, 'id');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsTrueForOriginalAttribute(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn(['status' => 'active']);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $this->controller->callHasModelAttribute($modelMock, 'status');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsTrueForCastAttribute(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn(['is_active' => 'boolean']);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $this->controller->callHasModelAttribute($modelMock, 'is_active');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsTrueForAppendedAttribute(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn(['full_name']);

        $result = $this->controller->callHasModelAttribute($modelMock, 'full_name');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsFalseForNonExistentAttribute(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn(['name']);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $this->controller->callHasModelAttribute($modelMock, 'non_existent');

        $this->assertFalse($result);
    }

    public function testHasModelAttributeDeduplicatesAttributes(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn(['name', 'email']);
        $modelMock->shouldReceive('getGuarded')->andReturn(['name']); // Duplicate
        $modelMock->shouldReceive('getOriginal')->andReturn(['name' => 'test']); // Duplicate
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $this->controller->callHasModelAttribute($modelMock, 'name');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeWithEmptyModel(): void
    {
        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $this->controller->callHasModelAttribute($modelMock, 'any_field');

        $this->assertFalse($result);
    }

    // =========================================================================
    // runValidation Tests
    // =========================================================================

    public function testRunValidationReturnsNullWhenRulesAreEmpty(): void
    {
        $result = $this->controller->callRunValidation(['name' => 'Test'], []);

        $this->assertNull($result);
    }

    public function testRunValidationReturnsNullWhenValidationPasses(): void
    {
        $messageBag = Mockery::mock(MessageBag::class);

        $validatorMock = Mockery::mock(ValidatorInstance::class);
        $validatorMock->shouldReceive('passes')->once()->andReturn(true);

        Validator::shouldReceive('make')
            ->once()
            ->with(['name' => 'John'], ['name' => 'required'])
            ->andReturn($validatorMock);

        $result = $this->controller->callRunValidation(
            ['name' => 'John'],
            ['name' => 'required']
        );

        $this->assertNull($result);
    }

    public function testRunValidationReturnsErrorResponseWhenValidationFails(): void
    {
        $messageBag = Mockery::mock(MessageBag::class);
        $messageBag->shouldReceive('toArray')->andReturn([
            'email' => ['The email field is required.']
        ]);

        $validatorMock = Mockery::mock(ValidatorInstance::class);
        $validatorMock->shouldReceive('passes')->once()->andReturn(false);
        $validatorMock->shouldReceive('errors')->once()->andReturn($messageBag);
        $validatorMock->shouldReceive('failed')->once()->andReturn([
            'email' => ['Required' => []]
        ]);

        Validator::shouldReceive('make')
            ->once()
            ->with(['email' => ''], ['email' => 'required'])
            ->andReturn($validatorMock);

        $result = $this->controller->callRunValidation(
            ['email' => ''],
            ['email' => 'required']
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(400, $result->getStatusCode());

        $this->assertCount(1, $this->controller->failData);
        $this->assertSame('email', $this->controller->failData[0]['field']);
        $this->assertSame('required', $this->controller->failData[0]['error']);
        $this->assertSame('', $this->controller->failData[0]['value']);
        $this->assertSame('The email field is required.', $this->controller->failData[0]['message']);
    }

    public function testRunValidationHandlesMultipleFieldErrors(): void
    {
        $messageBag = Mockery::mock(MessageBag::class);
        $messageBag->shouldReceive('toArray')->andReturn([
            'name' => ['The name field is required.'],
            'email' => ['The email field is required.']
        ]);

        $validatorMock = Mockery::mock(ValidatorInstance::class);
        $validatorMock->shouldReceive('passes')->once()->andReturn(false);
        $validatorMock->shouldReceive('errors')->once()->andReturn($messageBag);
        $validatorMock->shouldReceive('failed')->once()->andReturn([
            'name' => ['Required' => []],
            'email' => ['Required' => []]
        ]);

        Validator::shouldReceive('make')
            ->once()
            ->andReturn($validatorMock);

        $result = $this->controller->callRunValidation(
            [],
            ['name' => 'required', 'email' => 'required']
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertCount(2, $this->controller->failData);

        $this->assertSame('name', $this->controller->failData[0]['field']);
        $this->assertSame('required', $this->controller->failData[0]['error']);

        $this->assertSame('email', $this->controller->failData[1]['field']);
        $this->assertSame('required', $this->controller->failData[1]['error']);
    }

    public function testRunValidationHandlesMultipleRulesPerField(): void
    {
        $messageBag = Mockery::mock(MessageBag::class);
        $messageBag->shouldReceive('toArray')->andReturn([
            'email' => [
                'The email field is required.',
                'The email field must be a valid email address.'
            ]
        ]);

        $validatorMock = Mockery::mock(ValidatorInstance::class);
        $validatorMock->shouldReceive('passes')->once()->andReturn(false);
        $validatorMock->shouldReceive('errors')->once()->andReturn($messageBag);
        $validatorMock->shouldReceive('failed')->once()->andReturn([
            'email' => [
                'Required' => [],
                'Email' => []
            ]
        ]);

        Validator::shouldReceive('make')
            ->once()
            ->andReturn($validatorMock);

        $result = $this->controller->callRunValidation(
            ['email' => ''],
            ['email' => 'required|email']
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertCount(2, $this->controller->failData);

        $this->assertSame('email', $this->controller->failData[0]['field']);
        $this->assertSame('required', $this->controller->failData[0]['error']);
        $this->assertSame('The email field is required.', $this->controller->failData[0]['message']);

        $this->assertSame('email', $this->controller->failData[1]['field']);
        $this->assertSame('email', $this->controller->failData[1]['error']);
        $this->assertSame('The email field must be a valid email address.', $this->controller->failData[1]['message']);
    }

    public function testRunValidationHandlesMissingFieldValue(): void
    {
        $messageBag = Mockery::mock(MessageBag::class);
        $messageBag->shouldReceive('toArray')->andReturn([
            'name' => ['The name field is required.']
        ]);

        $validatorMock = Mockery::mock(ValidatorInstance::class);
        $validatorMock->shouldReceive('passes')->once()->andReturn(false);
        $validatorMock->shouldReceive('errors')->once()->andReturn($messageBag);
        $validatorMock->shouldReceive('failed')->once()->andReturn([
            'name' => ['Required' => []]
        ]);

        Validator::shouldReceive('make')
            ->once()
            ->andReturn($validatorMock);

        // Field 'name' is not in data array
        $result = $this->controller->callRunValidation(
            [],
            ['name' => 'required']
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertNull($this->controller->failData[0]['value']);
    }

    public function testRunValidationHandlesMissingErrorMessage(): void
    {
        $messageBag = Mockery::mock(MessageBag::class);
        $messageBag->shouldReceive('toArray')->andReturn([
            // No messages for 'name' field
        ]);

        $validatorMock = Mockery::mock(ValidatorInstance::class);
        $validatorMock->shouldReceive('passes')->once()->andReturn(false);
        $validatorMock->shouldReceive('errors')->once()->andReturn($messageBag);
        $validatorMock->shouldReceive('failed')->once()->andReturn([
            'name' => ['Required' => []]
        ]);

        Validator::shouldReceive('make')
            ->once()
            ->andReturn($validatorMock);

        $result = $this->controller->callRunValidation(
            ['name' => ''],
            ['name' => 'required']
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertNull($this->controller->failData[0]['message']);
    }

    public function testRunValidationIncludesSubmittedValue(): void
    {
        $messageBag = Mockery::mock(MessageBag::class);
        $messageBag->shouldReceive('toArray')->andReturn([
            'age' => ['The age field must be an integer.']
        ]);

        $validatorMock = Mockery::mock(ValidatorInstance::class);
        $validatorMock->shouldReceive('passes')->once()->andReturn(false);
        $validatorMock->shouldReceive('errors')->once()->andReturn($messageBag);
        $validatorMock->shouldReceive('failed')->once()->andReturn([
            'age' => ['Integer' => []]
        ]);

        Validator::shouldReceive('make')
            ->once()
            ->andReturn($validatorMock);

        $result = $this->controller->callRunValidation(
            ['age' => 'not-a-number'],
            ['age' => 'integer']
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame('not-a-number', $this->controller->failData[0]['value']);
    }

    public function testRunValidationConvertsRuleNameToLowercase(): void
    {
        $messageBag = Mockery::mock(MessageBag::class);
        $messageBag->shouldReceive('toArray')->andReturn([
            'email' => ['The email must be valid.']
        ]);

        $validatorMock = Mockery::mock(ValidatorInstance::class);
        $validatorMock->shouldReceive('passes')->once()->andReturn(false);
        $validatorMock->shouldReceive('errors')->once()->andReturn($messageBag);
        $validatorMock->shouldReceive('failed')->once()->andReturn([
            'email' => ['EMAIL' => []] // Uppercase rule name
        ]);

        Validator::shouldReceive('make')
            ->once()
            ->andReturn($validatorMock);

        $result = $this->controller->callRunValidation(
            ['email' => 'invalid'],
            ['email' => 'email']
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame('email', $this->controller->failData[0]['error']); // Should be lowercase
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
