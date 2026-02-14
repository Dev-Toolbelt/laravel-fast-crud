<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Traits;

use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use DevToolbelt\LaravelFastCrud\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Mockery\MockInterface;

final class SearchableTest extends TestCase
{
    use Searchable;

    protected string $termFieldName = 'term';

    /**
     * @var array<int, string>
     */
    protected array $termFields = ['login', 'name', 'email'];

    private Builder&MockInterface $queryMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queryMock = $this->createQueryMockWithConnection('pgsql');
    }

    private function createQueryMockWithConnection(string $driver): Builder&MockInterface
    {
        $connectionMock = Mockery::mock('Illuminate\Database\Connection');
        $connectionMock->shouldReceive('getDriverName')->andReturn($driver);

        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('getConnection')->andReturn($connectionMock);

        return $queryMock;
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

    public function testProcessSearchWithGreaterThanOrNullOperatorExecutesClosure(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('whereNull')
            ->once()
            ->with('stock')
            ->andReturnSelf();
        $innerQueryMock->shouldReceive('orWhere')
            ->once()
            ->with('stock', '>', '10')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['stock' => ['gtn' => '10']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithLesserThanOrNullOperatorExecutesClosure(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('whereNull')
            ->once()
            ->with('stock')
            ->andReturnSelf();
        $innerQueryMock->shouldReceive('orWhere')
            ->once()
            ->with('stock', '<', '10')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
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

    public function testProcessSearchWithRelationLikeFilterExecutesClosure(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('where')
            ->once()
            ->with('name', 'ILIKE', '%electronics%')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('category', Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['category.name' => ['like' => 'electronics']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationInFilterExecutesClosure(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('whereIn')
            ->once()
            ->with('id', ['1', '2', '3'])
            ->andReturnSelf();

        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('category', Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['category.id' => ['in' => '1,2,3']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationSimpleEqualityExecutesClosure(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('where')
            ->once()
            ->with('name', 'Electronics')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('category', Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['category.name' => 'Electronics']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationBetweenFilterExecutesClosure(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('whereBetween')
            ->once()
            ->with('created_at', ['2024-01-01 00:00:00', '2024-12-31 23:59:59'])
            ->andReturnSelf();

        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('order', Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
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

    public function testProcessSearchWithLikeOperatorUsesLikeForMysql(): void
    {
        $queryMock = $this->createQueryMockWithConnection('mysql');
        $queryMock->shouldReceive('where')
            ->once()
            ->with('name', 'LIKE', '%samsung%')
            ->andReturnSelf();

        $this->processSearch($queryMock, ['name' => ['like' => 'samsung']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithLikeOperatorUsesIlikeForPgsql(): void
    {
        $queryMock = $this->createQueryMockWithConnection('pgsql');
        $queryMock->shouldReceive('where')
            ->once()
            ->with('name', 'ILIKE', '%samsung%')
            ->andReturnSelf();

        $this->processSearch($queryMock, ['name' => ['like' => 'samsung']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithLikeOperatorUsesLikeForSqlite(): void
    {
        $queryMock = $this->createQueryMockWithConnection('sqlite');
        $queryMock->shouldReceive('where')
            ->once()
            ->with('name', 'LIKE', '%samsung%')
            ->andReturnSelf();

        $this->processSearch($queryMock, ['name' => ['like' => 'samsung']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationLikeFilterUsesMysqlLikeExecutesClosure(): void
    {
        $queryMock = $this->createQueryMockWithConnection('mysql');

        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('where')
            ->once()
            ->with('name', 'LIKE', '%electronics%')
            ->andReturnSelf();

        $queryMock->shouldReceive('whereHas')
            ->once()
            ->with('category', Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($queryMock, ['category.name' => ['like' => 'electronics']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithBooleanValue(): void
    {
        $this->queryMock->shouldReceive('where')
            ->once()
            ->with('is_active', true)
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['is_active' => true]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithBetweenOperatorMonthFormat(): void
    {
        $this->queryMock->shouldReceive('whereBetween')
            ->once()
            ->with('created_at', ['2024-01-01 00:00:00', '2024-03-31 23:59:59'])
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['created_at' => ['btw' => '2024-01,2024-03']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithBetweenOperatorSingleMonthFormat(): void
    {
        $this->queryMock->shouldReceive('whereBetween')
            ->once()
            ->with('created_at', ['2024-06-01 00:00:00', '2024-06-30 23:59:59'])
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['created_at' => ['btw' => '2024-06']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithThreeLevelRelationLikeFilterExecutesClosure(): void
    {
        $innerQueryMock2 = Mockery::mock(Builder::class);
        $innerQueryMock2->shouldReceive('where')
            ->once()
            ->with('name', 'ILIKE', '%John%')
            ->andReturnSelf();

        $innerQueryMock1 = Mockery::mock(Builder::class);
        $innerQueryMock1->shouldReceive('whereHas')
            ->once()
            ->with('customer', Mockery::on(function ($closure) use ($innerQueryMock2) {
                $closure($innerQueryMock2);
                return true;
            }))
            ->andReturnSelf();

        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('order', Mockery::on(function ($closure) use ($innerQueryMock1) {
                $closure($innerQueryMock1);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['order.customer.name' => ['like' => 'John']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithThreeLevelRelationSimpleEqualityExecutesClosure(): void
    {
        $innerQueryMock2 = Mockery::mock(Builder::class);
        $innerQueryMock2->shouldReceive('where')
            ->once()
            ->with('email', 'john@example.com')
            ->andReturnSelf();

        $innerQueryMock1 = Mockery::mock(Builder::class);
        $innerQueryMock1->shouldReceive('whereHas')
            ->once()
            ->with('customer', Mockery::on(function ($closure) use ($innerQueryMock2) {
                $closure($innerQueryMock2);
                return true;
            }))
            ->andReturnSelf();

        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('order', Mockery::on(function ($closure) use ($innerQueryMock1) {
                $closure($innerQueryMock1);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['order.customer.email' => 'john@example.com']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithInOperatorMoreThanTwoLevelRelation(): void
    {
        $this->queryMock->shouldReceive('whereIn')
            ->once()
            ->with('order.customer.id', ['1', '2', '3'])
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['order.customer.id' => ['in' => '1,2,3']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithRelationSimpleEqualityConvertsIdToExternalIdExecutesClosure(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('where')
            ->once()
            ->with('external_id', 'abc-123')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('category', Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['category.id' => 'abc-123']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithBetweenRelationMonthFormatExecutesClosure(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('whereBetween')
            ->once()
            ->with('created_at', ['2024-01-01 00:00:00', '2024-03-31 23:59:59'])
            ->andReturnSelf();

        $this->queryMock->shouldReceive('whereHas')
            ->once()
            ->with('order', Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['order.created_at' => ['btw' => '2024-01,2024-03']]);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithTermFilterUsesIlikeForPgsql(): void
    {
        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('where')
            ->once()
            ->with('login', 'ILIKE', '%john%')
            ->andReturnSelf();
        $innerQueryMock->shouldReceive('orWhere')
            ->once()
            ->with('name', 'ILIKE', '%john%')
            ->andReturnSelf();
        $innerQueryMock->shouldReceive('orWhere')
            ->once()
            ->with('email', 'ILIKE', '%john%')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['term' => 'john']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithTermFilterUsesLikeForMysql(): void
    {
        $queryMock = $this->createQueryMockWithConnection('mysql');

        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('where')
            ->once()
            ->with('login', 'LIKE', '%john%')
            ->andReturnSelf();
        $innerQueryMock->shouldReceive('orWhere')
            ->once()
            ->with('name', 'LIKE', '%john%')
            ->andReturnSelf();
        $innerQueryMock->shouldReceive('orWhere')
            ->once()
            ->with('email', 'LIKE', '%john%')
            ->andReturnSelf();

        $queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($queryMock, ['term' => 'john']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithCustomTermFieldName(): void
    {
        $this->termFieldName = 'q';

        $innerQueryMock = Mockery::mock(Builder::class);
        $innerQueryMock->shouldReceive('where')
            ->once()
            ->with('login', 'ILIKE', '%john%')
            ->andReturnSelf();
        $innerQueryMock->shouldReceive('orWhere')
            ->once()
            ->with('name', 'ILIKE', '%john%')
            ->andReturnSelf();
        $innerQueryMock->shouldReceive('orWhere')
            ->once()
            ->with('email', 'ILIKE', '%john%')
            ->andReturnSelf();

        $this->queryMock->shouldReceive('where')
            ->once()
            ->with(Mockery::on(function ($closure) use ($innerQueryMock) {
                $closure($innerQueryMock);
                return true;
            }))
            ->andReturnSelf();

        $this->processSearch($this->queryMock, ['q' => 'john']);
        $this->addToAssertionCount(1);
    }

    public function testProcessSearchWithTermFilterIgnoresEmptyValue(): void
    {
        $this->queryMock->shouldNotReceive('where');

        $this->processSearch($this->queryMock, ['term' => '   ']);
        $this->addToAssertionCount(1);
    }
}
