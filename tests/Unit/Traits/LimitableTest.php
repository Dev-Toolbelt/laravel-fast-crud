<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Traits;

use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use DevToolbelt\LaravelFastCrud\Traits\Limitable;
use Illuminate\Database\Eloquent\Builder;
use Mockery;

final class LimitableTest extends TestCase
{
    use Limitable;

    public function testProcessLimitAppliesLimitToQuery(): void
    {
        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('limit')
            ->once()
            ->with(10)
            ->andReturnSelf();

        $this->processLimit($queryMock, 10);

        $this->addToAssertionCount(1);
    }

    public function testProcessLimitWithNullDoesNotApplyLimit(): void
    {
        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldNotReceive('limit');

        $this->processLimit($queryMock, null);

        $this->addToAssertionCount(1);
    }

    public function testProcessLimitWithZeroDoesNotApplyLimit(): void
    {
        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldNotReceive('limit');

        $this->processLimit($queryMock, 0);

        $this->addToAssertionCount(1);
    }

    public function testProcessLimitWithNegativeValueDoesNotApplyLimit(): void
    {
        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldNotReceive('limit');

        $this->processLimit($queryMock, -5);

        $this->addToAssertionCount(1);
    }

    public function testProcessLimitWithPositiveValueAppliesLimit(): void
    {
        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('limit')
            ->once()
            ->with(100)
            ->andReturnSelf();

        $this->processLimit($queryMock, 100);

        $this->addToAssertionCount(1);
    }

    public function testProcessLimitWithOneAppliesLimit(): void
    {
        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('limit')
            ->once()
            ->with(1)
            ->andReturnSelf();

        $this->processLimit($queryMock, 1);

        $this->addToAssertionCount(1);
    }

    public function testProcessLimitWithLargeNumberAppliesLimit(): void
    {
        $queryMock = Mockery::mock(Builder::class);
        $queryMock->shouldReceive('limit')
            ->once()
            ->with(999999)
            ->andReturnSelf();

        $this->processLimit($queryMock, 999999);

        $this->addToAssertionCount(1);
    }
}
