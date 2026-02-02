<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Traits;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

trait Sortable
{
    public function processSort(Builder $query, string $sort = ''): void
    {
        if (empty($sort)) {
            return;
        }

        $columns = explode(',', $sort);

        foreach ($columns as $column) {
            $columnName = Str::snake($column);
            if (str_contains($columnName, '-')) {
                $key = explode('-', $columnName);
                $query->orderBy($key[1], "DESC");
                continue;
            }
            $query->orderBy($columnName, "ASC");
        }
    }
}
