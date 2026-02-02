<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests;

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
