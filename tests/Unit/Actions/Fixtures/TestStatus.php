<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions\Fixtures;

enum TestStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
