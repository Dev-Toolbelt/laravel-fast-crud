<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit;

use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Mockery;

final class CrudControllerTest extends TestCase
{
    public function testHasModelAttributeReturnsTrueForFillableAttribute(): void
    {
        $controller = $this->createController();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn(['name', 'email']);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $controller->callHasModelAttribute($modelMock, 'name');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsTrueForGuardedAttribute(): void
    {
        $controller = $this->createController();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn(['id', 'created_at']);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $controller->callHasModelAttribute($modelMock, 'id');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsTrueForOriginalAttribute(): void
    {
        $controller = $this->createController();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn(['status' => 'active']);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $controller->callHasModelAttribute($modelMock, 'status');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsTrueForCastAttribute(): void
    {
        $controller = $this->createController();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn(['is_active' => 'boolean']);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $controller->callHasModelAttribute($modelMock, 'is_active');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsTrueForAppendedAttribute(): void
    {
        $controller = $this->createController();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn(['full_name']);

        $result = $controller->callHasModelAttribute($modelMock, 'full_name');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeReturnsFalseForNonExistentAttribute(): void
    {
        $controller = $this->createController();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn(['name']);
        $modelMock->shouldReceive('getGuarded')->andReturn(['id']);
        $modelMock->shouldReceive('getOriginal')->andReturn(['status' => 'active']);
        $modelMock->shouldReceive('getCasts')->andReturn(['is_active' => 'boolean']);
        $modelMock->shouldReceive('getAppends')->andReturn(['full_name']);

        $result = $controller->callHasModelAttribute($modelMock, 'non_existent_field');

        $this->assertFalse($result);
    }

    public function testHasModelAttributeDeduplicatesAttributes(): void
    {
        $controller = $this->createController();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn(['name', 'email']);
        $modelMock->shouldReceive('getGuarded')->andReturn(['name']); // Duplicate
        $modelMock->shouldReceive('getOriginal')->andReturn(['name' => 'test']); // Duplicate
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $controller->callHasModelAttribute($modelMock, 'name');

        $this->assertTrue($result);
    }

    public function testHasModelAttributeWithEmptyModel(): void
    {
        $controller = $this->createController();

        $modelMock = Mockery::mock(Model::class);
        $modelMock->shouldReceive('getFillable')->andReturn([]);
        $modelMock->shouldReceive('getGuarded')->andReturn([]);
        $modelMock->shouldReceive('getOriginal')->andReturn([]);
        $modelMock->shouldReceive('getCasts')->andReturn([]);
        $modelMock->shouldReceive('getAppends')->andReturn([]);

        $result = $controller->callHasModelAttribute($modelMock, 'any_field');

        $this->assertFalse($result);
    }

    private function createController(): object
    {
        return new class {
            protected function hasModelAttribute(Model $model, string $attributeName): bool
            {
                $attributes = array_unique([
                    ...$model->getFillable(),
                    ...$model->getGuarded(),
                    ...array_keys($model->getOriginal()),
                    ...array_keys($model->getCasts()),
                    ...$model->getAppends(),
                ]);

                return in_array($attributeName, $attributes, true);
            }

            public function callHasModelAttribute(Model $model, string $attributeName): bool
            {
                return $this->hasModelAttribute($model, $attributeName);
            }
        };
    }
}
