<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Enum;

use DevToolbelt\LaravelFastCrud\Enum\SearchOperator;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;

final class SearchOperatorTest extends TestCase
{
    public function testEqualOperator(): void
    {
        $this->assertSame('eq', SearchOperator::EQUAL->value);
    }

    public function testNotEqualOperator(): void
    {
        $this->assertSame('neq', SearchOperator::NOT_EQUAL->value);
    }

    public function testInOperator(): void
    {
        $this->assertSame('in', SearchOperator::IN->value);
    }

    public function testNotInOperator(): void
    {
        $this->assertSame('nin', SearchOperator::NOT_IN->value);
    }

    public function testLikeOperator(): void
    {
        $this->assertSame('like', SearchOperator::LIKE->value);
    }

    public function testLessThanOperator(): void
    {
        $this->assertSame('lt', SearchOperator::LESS_THAN->value);
    }

    public function testGreaterThanOperator(): void
    {
        $this->assertSame('gt', SearchOperator::GREATER_THAN->value);
    }

    public function testLessThanEqualOperator(): void
    {
        $this->assertSame('lte', SearchOperator::LESS_THAN_EQUAL->value);
    }

    public function testGreaterThanEqualOperator(): void
    {
        $this->assertSame('gte', SearchOperator::GREATER_THAN_EQUAL->value);
    }

    public function testGreaterThanOrNullOperator(): void
    {
        $this->assertSame('gtn', SearchOperator::GREATER_THAN_OR_NULL->value);
    }

    public function testLesserThanOrNullOperator(): void
    {
        $this->assertSame('ltn', SearchOperator::LESSER_THAN_OR_NULL->value);
    }

    public function testBetweenOperator(): void
    {
        $this->assertSame('btw', SearchOperator::BETWEEN->value);
    }

    public function testJsonOperator(): void
    {
        $this->assertSame('json', SearchOperator::JSON->value);
    }

    public function testNotNullOperator(): void
    {
        $this->assertSame('nn', SearchOperator::NOT_NULL->value);
    }

    public function testCanCreateFromValue(): void
    {
        $operator = SearchOperator::from('eq');
        $this->assertSame(SearchOperator::EQUAL, $operator);
    }

    public function testAllOperatorsCount(): void
    {
        $this->assertCount(14, SearchOperator::cases());
    }

    public function testTryFromWithValidValue(): void
    {
        $operator = SearchOperator::tryFrom('like');
        $this->assertSame(SearchOperator::LIKE, $operator);
    }

    public function testTryFromWithInvalidValue(): void
    {
        $operator = SearchOperator::tryFrom('invalid');
        $this->assertNull($operator);
    }
}
