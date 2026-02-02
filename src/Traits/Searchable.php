<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Traits;

use DevToolbelt\LaravelFastCrud\Enum\SearchOperator;
use Exception;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * @throws Exception
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
                foreach ($param as $operator => $value) {
                    if ($operator === SearchOperator::NOT_NULL->value) {
                        $query->whereNotNull($column);
                        continue;
                    }

                    if (empty($value)) {
                        continue;
                    }

                    $value = !is_array($value) ? trim($value) : $value;
                    $operator = SearchOperator::from($operator);

                    match ($operator) {
                        SearchOperator::NOT_NULL => $query->whereNotNull($column),
                        SearchOperator::EQUAL => $query->where($column, $value),
                        SearchOperator::NOT_EQUAL => $query->whereNot($column, $value),
                        SearchOperator::LESS_THAN => $query->where($column, '<', $value),
                        SearchOperator::LESS_THAN_EQUAL => $query->where($column, '<=', $value),
                        SearchOperator::LESSER_THAN_OR_NULL =>
                            $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, '<', $value)),
                        SearchOperator::GREATER_THAN => $query->where($column, '>', $value),
                        SearchOperator::GREATER_THAN_EQUAL => $query->where($column, '>=', $value),
                        SearchOperator::GREATER_THAN_OR_NULL =>
                            $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, '>', $value)),
                        SearchOperator::IN => $this->inFilter($query, $column, $value, $hasRelation),
                        SearchOperator::NOT_IN => $query->whereNotIn($column, explode(',', $value)),
                        SearchOperator::LIKE => $this->likeFilter($query, $column, $value, $hasRelation),
                        SearchOperator::BETWEEN => $this->betweenFilter($query, $column, $value, $hasRelation),
                        SearchOperator::JSON => $this->applyJsonFilter($query, $column, $value),
                    };
                }
            } elseif (is_string($param)) {
                $this->applySimpleEquality($query, $column, $param, $hasRelation);
            } else {
                $query->where($column, $param);
            }
        }
    }

    private function inFilter(Builder $query, string $column, string $value, bool $hasRelation): void
    {
        $values = explode(',', $value);

        if ($hasRelation && count(explode('.', $column)) <= 2) {
            [$relation, $field] = explode('.', $column);
            $query->whereHas(Str::camel($relation), fn ($q) => $q->whereIn($field, $values));
            return;
        }

        $query->whereIn($column, $values);
    }

    private function likeFilter(Builder $query, string $column, string $value, bool $hasRelation): void
    {
        if ($hasRelation) {
            $parts = explode('.', $column);
            if (count($parts) <= 2) {
                [$relation, $column] = $parts;
                $query->whereHas(Str::camel($relation), fn ($q) => $q->where($column, 'ILIKE', "%$value%"));
            } else {
                [$relation1, $relation2, $column] = $parts;
                $query->whereHas($relation1, fn ($q) =>
                $q->whereHas($relation2, fn ($q2) => $q2->where($column, 'ILIKE', "%$value%")));
            }
            return;
        }

        $query->where($column, 'ILIKE', "%$value%");
    }

    /**
     * @throws Exception
     */
    private function betweenFilter(Builder $query, string $column, string $value, bool $hasRelation): void
    {
        $dates = explode(',', $value);

        $date1 = strlen($dates[0]) === 7
            ? (new DateTimeImmutable($dates[0]))->format('Y-m-01 00:00:00')
            : "$dates[0] 00:00:00";

        $date2 = isset($dates[1])
            ? (strlen($dates[0]) === 7
                ? (new DateTimeImmutable($dates[1]))->format('Y-m-t 23:59:59')
                : "$dates[1] 23:59:59")
            : (strlen($dates[0]) === 7
                ? (new DateTimeImmutable($dates[0]))->format('Y-m-t 23:59:59')
                : "$dates[0] 23:59:59");

        if ($hasRelation && count(explode('.', $column)) <= 2) {
            [$relation, $field] = explode('.', $column);
            $query->whereHas(Str::camel($relation), fn ($q) => $q->whereBetween($field, [$date1, $date2]));
            return;
        }

        $query->whereBetween($column, [$date1, $date2]);
    }

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

    private function applySimpleEquality(Builder $query, string $column, string $value, bool $hasRelation): void
    {
        if ($hasRelation) {
            $parts = explode('.', $column);
            if (count($parts) === 2) {
                [$relation, $field] = $parts;
                $query->whereHas(Str::camel($relation), fn ($q) =>
                $q->where($field === 'id' ? 'external_id' : $field, $value));
            } elseif (count($parts) === 3) {
                [$r1, $r2, $field] = $parts;
                $query->whereHas($r1, fn ($q) =>
                $q->whereHas($r2, fn ($q2) =>
                $q2->where($field, $value)));
            }
            return;
        }

        $query->where($column, $value);
    }
}
