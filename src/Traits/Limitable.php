<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides query limit functionality for Eloquent queries.
 *
 * Simple trait to apply a LIMIT clause to queries.
 */
trait Limitable
{
    /**
     * Applies a LIMIT clause to the query.
     *
     * @param Builder $query The query builder instance
     * @param int|null $limit Maximum number of records to return (null or 0 to skip)
     */
    public function processLimit(Builder $query, ?int $limit = null): void
    {
        if ($limit === null || $limit <= 0) {
            return;
        }

        $query->limit($limit);
    }
}
