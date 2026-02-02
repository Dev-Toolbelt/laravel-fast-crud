<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Traits;

use DevToolbelt\LaravelFastCrud\Traits\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
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

    public function testRunValidationReturnsNullWhenRulesAreEmpty(): void
    {
        $result = $this->controller->callRunValidation(['name' => 'Test'], []);

        $this->assertNull($result);
    }

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
}
