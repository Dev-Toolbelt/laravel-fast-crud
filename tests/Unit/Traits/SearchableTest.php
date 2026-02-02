<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Traits;

use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use DevToolbelt\LaravelFastCrud\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Mockery;

final class SearchableTest extends TestCase
{
    use Searchable;

    private Builder $queryMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryMock = Mockery::mock(Builder::class);
    }

    public function testProcessSearchWithEmptyFilters(): void
    {
        $this->queryMock->shouldNotReceive('where');
        $this->processSearch($this->queryMock, []);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithSimpleStringValue(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('status', 'active')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['status' => 'active']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithNumericValue(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('category_id', 5)
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['category_id' => 5]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchConvertsCamelCaseToSnakeCase(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('category_id', 'test')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['categoryId' => 'test']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithEqualOperator(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('status', 'active')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['status' => ['eq' => 'active']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithNotEqualOperator(): void
    {
        $this->queryMock->shouldReceive('whereNot')
            ->once()
            ->with('status', 'deleted')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['status' => ['neq' => 'deleted']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithLessThanOperator(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('price', '<', '100')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['price' => ['lt' => '100']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithLessThanEqualOperator(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('price', '<=', '100')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['price' => ['lte' => '100']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithGreaterThanOperator(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('price', '>', '100')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['price' => ['gt' => '100']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithGreaterThanEqualOperator(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('price', '>=', '100')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['price' => ['gte' => '100']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithNotInOperator(): void
    {
        $this->queryMock->shouldReceive('whereNotIn')
            ->once()
            ->with('status', ['deleted', 'archived'])
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['status' => ['nin' => 'deleted,archived']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithInOperator(): void
    {
        $this->queryMock->shouldReceive('whereIn')
            ->once()
            ->with('status', ['active', 'pending'])
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['status' => ['in' => 'active,pending']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithLikeOperator(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('name', 'ILIKE', '%samsung%')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['name' => ['like' => 'samsung']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithNotNullOperator(): void
    {
        $this->queryMock->shouldReceive('whereNotNull')
            ->once()
            ->with('deleted_at')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['deleted_at' => ['nn' => '1']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithBetweenOperator(): void
    {
        $this->queryMock->shouldReceive('whereBetween')
            ->once()
            ->with('created_at', ['2024-01-01 00:00:00', '2024-12-31 23:59:59'])
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['created_at' => ['btw' => '2024-01-01,2024-12-31']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithBetweenOperatorSingleDate(): void
    {
        $this->queryMock->shouldReceive('whereBetween')
            ->once()
            ->with('created_at', ['2024-01-15 00:00:00', '2024-01-15 23:59:59'])
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['created_at' => ['btw' => '2024-01-15']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithGreaterThanOrNullOperator(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::type('Closure'))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['stock' => ['gtn' => '10']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithLesserThanOrNullOperator(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::type('Closure'))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['stock' => ['ltn' => '10']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithJsonOperator(): void
    {
        $this->queryMock->shouldReceive('whereJsonContains')
            ->once()
            ->with('metadata', ['type' => 'premium'])
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['metadata' => ['json' => ['type' => 'premium']]]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithJsonOperatorMultipleValues(): void
    {
        $this->queryMock->shouldReceive('whereJsonContains')
            ->times(2)
            ->with('tags', Mockery::type('array'), 'or')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['tags' => ['json' => ['type' => 'electronics,featured']]]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchIgnoresEmptyValues(): void
    {
        $this->queryMock->shouldNotReceive('where');
        $this->queryMock->shouldNotReceive('whereIn');

        $this->processSearch($this->queryMock, ['name' => ['like' => '']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithMultipleFilters(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('status', 'active')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('price', '>=', '100')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, [
            'status' => ['eq' => 'active'],
            'price' => ['gte' => '100'],
        ]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationLikeFilter(): void
    {
        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('category', Mockery::type('Closure'))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['category.name' => ['like' => 'electronics']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationInFilter(): void
    {
        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('category', Mockery::type('Closure'))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['category.id' => ['in' => '1,2,3']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationSimpleEquality(): void
    {
        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('category', Mockery::type('Closure'))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['category.name' => 'Electronics']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationBetweenFilter(): void
    {
        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('order', Mockery::type('Closure'))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['order.created_at' => ['btw' => '2024-01-01,2024-12-31']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchTrimsValues(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('name', 'ILIKE', '%test%')
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['name' => ['like' => '  test  ']]);
        $this->addToAssertionCount(1);
    }
}
