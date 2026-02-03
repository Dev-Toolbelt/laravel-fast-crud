<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Traits;

use DateTimeImmutable;
use DevToolbelt\LaravelFastCrud\Enum\SearchOperator;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Provides flexible search/filter functionality for Eloquent queries.
 *
 * Supports multiple operators, relation filtering, and various data types.
 * Column names are automatically converted from camelCase to snake_case.
 *
 * @example
 * ```
 * // Simple equality
 * GET /products?filter[status]=active
 *
 * // With operators
 * GET /products?filter[name][like]=Samsung&filter[price][gte]=100
 *
 * // Relation filtering
 * GET /products?filter[category.name][like]=Electronics
 *
 * // Date range
 * GET /orders?filter[created_at][btw]=2024-01-01,2024-12-31
 * ```
 *
 * @see SearchOperator For available filter operators
 */
trait Searchable
{
    /**
     * Processes filter parameters and applies them to the query.
     *
     * @param Builder $query The query builder instance
     * @param array<string, mixed> $filters Filter parameters from the request
     *
     * @throws Exception When date parsing fails in BETWEEN filter
     */
    public function processSearch(Builder $query, array $filters = []): void
    {
        if (empty($filters)) {
            return;
        }

        foreach ($filters as $column => $param) {
            $column = Str::snake($column);
            $hasRelation = str_contains($column, '.');

            if (is_array($param)) {
                $this->applyOperatorFilters($query, $column, $param, $hasRelation);
                continue;
            }

            if (is_string($param)) {
                $this->applySimpleEquality($query, $column, $param, $hasRelation);
                continue;
            }

            $query->where($column, $param);
        }
    }

    /**
     * Applies filters with explicit operators.
     *
     * @param Builder $query The query builder instance
     * @param string $column The column name (snake_case)
     * @param array<string, mixed> $params Operator-value pairs
     * @param bool $hasRelation Whether the column references a relation
     *
     * @throws Exception When date parsing fails
     */
    private function applyOperatorFilters(Builder $query, string $column, array $params, bool $hasRelation): void
    {
        foreach ($params as $operatorKey => $value) {
            if ($operatorKey === SearchOperator::NOT_NULL->value) {
                $query->whereNotNull($column);
                continue;
            }

            if (empty($value)) {
                continue;
            }

            $value = !is_array($value) ? trim($value) : $value;
            $operator = SearchOperator::from($operatorKey);

            match ($operator) {
                SearchOperator::NOT_NULL => $query->whereNotNull($column),
                SearchOperator::EQUAL => $query->where($column, $value),
                SearchOperator::NOT_EQUAL => $query->whereNot($column, $value),
                SearchOperator::LESS_THAN => $query->where($column, '<', $value),
                SearchOperator::LESS_THAN_EQUAL => $query->where($column, '<=', $value),
                SearchOperator::LESSER_THAN_OR_NULL => $query->where(
                    fn(Builder $q): Builder => $q->whereNull($column)->orWhere($column, '<', $value)
                ),
                SearchOperator::GREATER_THAN => $query->where($column, '>', $value),
                SearchOperator::GREATER_THAN_EQUAL => $query->where($column, '>=', $value),
                SearchOperator::GREATER_THAN_OR_NULL => $query->where(
                    fn(Builder $q): Builder => $q->whereNull($column)->orWhere($column, '>', $value)
                ),
                SearchOperator::IN => $this->applyInFilter($query, $column, $value, $hasRelation),
                SearchOperator::NOT_IN => $query->whereNotIn($column, explode(',', $value)),
                SearchOperator::LIKE => $this->applyLikeFilter($query, $column, $value, $hasRelation),
                SearchOperator::BETWEEN => $this->applyBetweenFilter($query, $column, $value, $hasRelation),
                SearchOperator::JSON => $this->applyJsonFilter($query, $column, $value),
            };
        }
    }

    /**
     * Applies IN filter with relation support.
     *
     * @param Builder $query The query builder instance
     * @param string $column The column name
     * @param string $value Comma-separated values
     * @param bool $hasRelation Whether the column references a relation
     */
    private function applyInFilter(Builder $query, string $column, string $value, bool $hasRelation): void
    {
        $values = explode(',', $value);

        if ($hasRelation && count(explode('.', $column)) <= 2) {
            [$relation, $field] = explode('.', $column);
            $query->whereHas(Str::camel($relation), fn(Builder $q): Builder => $q->whereIn($field, $values));
            return;
        }

        $query->whereIn($column, $values);
    }

    /**
     * Applies case-insensitive LIKE filter with relation support.
     *
     * Supports up to 2 levels of nested relations.
     * Uses ILIKE for PostgreSQL and LIKE for MySQL/other databases.
     *
     * @param Builder $query The query builder instance
     * @param string $column The column name (may include relation path)
     * @param string $value The search value
     * @param bool $hasRelation Whether the column references a relation
     */
    private function applyLikeFilter(Builder $query, string $column, string $value, bool $hasRelation): void
    {
        $likeOperator = $this->getLikeOperator($query);

        if ($hasRelation) {
            $parts = explode('.', $column);
            if (count($parts) === 2) {
                [$relation, $field] = $parts;
                $query->whereHas(
                    Str::camel($relation),
                    fn(Builder $q): Builder => $q->where($field, $likeOperator, "%{$value}%")
                );
            } elseif (count($parts) === 3) {
                [$relation1, $relation2, $field] = $parts;
                $query->whereHas(
                    $relation1,
                    fn(Builder $q): Builder => $q->whereHas(
                        $relation2,
                        fn(Builder $q2): Builder => $q2->where($field, $likeOperator, "%{$value}%")
                    )
                );
            }
            return;
        }

        $query->where($column, $likeOperator, "%{$value}%");
    }

    /**
     * Gets the appropriate LIKE operator based on the database driver.
     *
     * Returns ILIKE for PostgreSQL (case-insensitive) and LIKE for other databases.
     * MySQL LIKE is case-insensitive by default with standard collations.
     *
     * @param Builder $query The query builder instance
     * @return string The LIKE operator to use
     */
    private function getLikeOperator(Builder $query): string
    {
        $driver = $query->getConnection()->getDriverName();

        return $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    /**
     * Applies BETWEEN filter for date ranges.
     *
     * Supports two formats:
     * - Full date: "2024-01-01,2024-12-31"
     * - Month format: "2024-01" (expands to full month range)
     * - Single date: "2024-01-15" (searches entire day)
     *
     * @param Builder $query The query builder instance
     * @param string $column The column name
     * @param string $value Comma-separated date values
     * @param bool $hasRelation Whether the column references a relation
     *
     * @throws Exception When date parsing fails
     */
    private function applyBetweenFilter(Builder $query, string $column, string $value, bool $hasRelation): void
    {
        $dates = explode(',', $value);
        $isMonthFormat = strlen($dates[0]) === 7;

        $date1 = $isMonthFormat
            ? (new DateTimeImmutable($dates[0]))->format('Y-m-01 00:00:00')
            : "{$dates[0]} 00:00:00";

        $date2 = isset($dates[1])
            ? ($isMonthFormat
                ? (new DateTimeImmutable($dates[1]))->format('Y-m-t 23:59:59')
                : "{$dates[1]} 23:59:59")
            : ($isMonthFormat
                ? (new DateTimeImmutable($dates[0]))->format('Y-m-t 23:59:59')
                : "{$dates[0]} 23:59:59");

        if ($hasRelation && count(explode('.', $column)) <= 2) {
            [$relation, $field] = explode('.', $column);
            $query->whereHas(
                Str::camel($relation),
                fn(Builder $q): Builder => $q->whereBetween($field, [$date1, $date2])
            );
            return;
        }

        $query->whereBetween($column, [$date1, $date2]);
    }

    /**
     * Applies JSON column filter using whereJsonContains.
     *
     * @param Builder $query The query builder instance
     * @param string $column The JSON column name
     * @param array<string, string> $value Key-value pair to search for in JSON
     */
    private function applyJsonFilter(Builder $query, string $column, array $value): void
    {
        $key = array_key_first($value);
        $val = $value[$key];

        if (str_contains($val, ',')) {
            $values = explode(',', $val);
            foreach ($values as $field) {
                $query->whereJsonContains($column, [$key => $field], 'or');
            }
        } else {
            $query->whereJsonContains($column, [$key => $val]);
        }
    }

    /**
     * Applies simple equality filter with relation support.
     *
     * For relation fields named 'id', automatically converts to 'external_id'.
     *
     * @param Builder $query The query builder instance
     * @param string $column The column name (may include relation path)
     * @param string $value The value to match
     * @param bool $hasRelation Whether the column references a relation
     */
    private function applySimpleEquality(Builder $query, string $column, string $value, bool $hasRelation): void
    {
        if ($hasRelation) {
            $parts = explode('.', $column);
            if (count($parts) === 2) {
                [$relation, $field] = $parts;
                $query->whereHas(
                    Str::camel($relation),
                    fn(Builder $q): Builder => $q->where($field === 'id' ? 'external_id' : $field, $value)
                );
            } elseif (count($parts) === 3) {
                [$r1, $r2, $field] = $parts;
                $query->whereHas(
                    $r1,
                    fn(Builder $q): Builder => $q->whereHas(
                        $r2,
                        fn(Builder $q2): Builder => $q2->where($field, $value)
                    )
                );
            }
            return;
        }

        $query->where($column, $value);
    }
}
