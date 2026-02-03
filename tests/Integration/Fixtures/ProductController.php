<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Integration\Fixtures;

use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;

class ProductController extends CrudController
{
    protected string $csvFileName = 'products.csv';

    protected array $csvColumns = [
        'id' => 'ID',
        'name' => 'Name',
        'price' => 'Price',
        'status' => 'Status',
    ];

    protected function modelClassName(): string
    {
        return Product::class;
    }

    protected function createValidateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function updateValidateRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    protected function modifySearchQuery(Builder $query): void
    {
        $query->whereNull('deleted_at');
    }
}
