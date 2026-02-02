<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Limitable
{
    public function processLimit(Builder $query, ?int $limit = null): void
    {
        if (empty($limit)) {
            return;
        }

        $query->limit($limit);
    }
}
