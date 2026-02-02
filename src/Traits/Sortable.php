<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Provides sorting functionality for Eloquent queries.
 *
 * Supports multiple columns and ascending/descending order.
 * Column names are automatically converted from camelCase to snake_case.
 *
 * @example
 * ```
 * // Single column ascending
 * GET /products?sort=name
 *
 * // Single column descending (prefix with -)
 * GET /products?sort=-created_at
 *
 * // Multiple columns
 * GET /products?sort=category,-price,name
 * ```
 */
trait Sortable
{
    /**
     * Processes sort parameter and applies ORDER BY clauses to the query.
     *
     * @param Builder $query The query builder instance
     * @param string $sort Comma-separated column names (prefix with - for DESC)
     */
    public function processSort(Builder $query, string $sort = ''): void
    {
        if ($sort === '') {
            return;
        }

        $columns = explode(',', $sort);

        foreach ($columns as $column) {
            $column = trim($column);

            if ($column === '') {
                continue;
            }

            if (str_starts_with($column, '-')) {
                $columnName = Str::snake(substr($column, 1));
                $query->orderBy($columnName, 'DESC');
                continue;
            }

            $columnName = Str::snake($column);
            $query->orderBy($columnName, 'ASC');
        }
    }
}
