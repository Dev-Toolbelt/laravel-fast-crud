<?php

declare(strict_types=1);

namespace DevToolbelt\Enums\Tests;

use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
