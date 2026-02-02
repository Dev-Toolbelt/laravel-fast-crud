# Laravel Fast CRUD

A powerful Laravel package for rapid CRUD API scaffolding. Build complete RESTful APIs in minutes with minimal boilerplate.

## Features

- **9 Pre-built Actions** - Create, Read, Update, Delete, Soft Delete, Restore, Search, Options, Export CSV
- **Flexible Filtering** - 14+ search operators (eq, like, between, in, json, etc.)
- **Automatic Routing** - Single line to register all CRUD routes with permissions
- **Hook System** - Customize behavior without overriding entire methods
- **Configurable** - Global and per-action configuration
- **Soft Delete with Audit** - Track who deleted records and when
- **CSV Export** - Export filtered data with custom column mapping
- **JSend Responses** - Consistent API response format

## Requirements

- PHP ^8.1
- Laravel ^11.0

## Installation

```bash
composer require dev-toolbelt/laravel-fast-crud
```

Publish the configuration (optional):

```bash
php artisan vendor:publish --tag=fast-crud-config
```

## Quick Start

### 1. Create a Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;

class ProductController extends CrudController
{
    protected function modelClassName(): string
    {
        return Product::class;
    }
}
```

### 2. Register Routes

```php
use App\Http\Controllers\ProductController;
use DevToolbelt\LaravelFastCrud\Router;

Router::crud('products', ProductController::class, 'products');
```

That's it! You now have a complete CRUD API:

| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | `/products` | search | List with filtering, sorting, pagination |
| POST | `/products` | create | Create a new record |
| GET | `/products/{id}` | read | Get a single record |
| PUT/PATCH | `/products/{id}` | update | Update a record |
| DELETE | `/products/{id}` | delete | Permanently delete a record |
| DELETE | `/products/{id}/soft` | softDelete | Soft delete a record |
| PATCH/PUT | `/products/{id}/restore` | restore | Restore a soft deleted record |
| GET | `/products/options` | options | Get label-value pairs for dropdowns |
| GET | `/products/export-csv` | exportCsv | Export to CSV file |

## Documentation

Detailed documentation for each action:

- [Create Action](docs/create.md) - Creating records with hooks
- [Read Action](docs/read.md) - Reading single records
- [Update Action](docs/update.md) - Updating records with hooks
- [Delete Action](docs/delete.md) - Permanent deletion
- [Soft Delete Action](docs/soft-delete.md) - Soft deletion with audit
- [Restore Action](docs/restore.md) - Restoring soft deleted records
- [Search Action](docs/search.md) - Filtering, sorting, pagination
- [Options Action](docs/options.md) - Dropdown data
- [Export CSV Action](docs/export-csv.md) - CSV export

## Configuration

```php
// config/devToolbelt/fast-crud.php

return [
    // Global settings (applied to all actions)
    'global' => [
        'find_field' => 'id',           // Field to find records by
        'find_field_is_uuid' => false,  // Validate as UUID
    ],

    // Per-action settings (override global)
    'create' => ['method' => 'toArray'],
    'read' => ['method' => 'toArray'],
    'update' => ['method' => 'toArray'],
    'delete' => [],
    'soft_delete' => [
        'deleted_at_field' => 'deleted_at',
        'deleted_by_field' => 'deleted_by',
    ],
    'restore' => ['method' => 'toArray'],
    'search' => ['method' => 'toArray', 'per_page' => 40],
    'export_csv' => ['method' => 'toArray'],
];
```

## Search Operators

Filter your data with powerful operators:

```
GET /products?filter[name][like]=Samsung&filter[price][gte]=100&filter[status][in]=active,pending
```

| Operator | Description | Example |
|----------|-------------|---------|
| `eq` | Equals | `filter[status][eq]=active` |
| `neq` | Not equals | `filter[status][neq]=deleted` |
| `like` | Contains (LIKE %value%) | `filter[name][like]=phone` |
| `in` | In list | `filter[status][in]=a,b,c` |
| `nin` | Not in list | `filter[status][nin]=x,y` |
| `gt` | Greater than | `filter[price][gt]=100` |
| `gte` | Greater than or equal | `filter[price][gte]=100` |
| `lt` | Less than | `filter[price][lt]=500` |
| `lte` | Less than or equal | `filter[price][lte]=500` |
| `btw` | Between | `filter[price][btw]=100,500` |
| `nn` | Not null | `filter[deleted_at][nn]=1` |
| `json` | JSON contains | `filter[tags][json]=electronics` |

## Sorting

Sort by one or multiple fields:

```
GET /products?sort=name              # ASC by name
GET /products?sort=-created_at       # DESC by created_at
GET /products?sort=category,-price   # ASC category, DESC price
```

## Pagination

```
GET /products?perPage=20             # 20 items per page
GET /products?skipPagination=true    # Return all records
```

## Customization with Hooks

Override hooks to customize behavior:

```php
class ProductController extends CrudController
{
    protected function modelClassName(): string
    {
        return Product::class;
    }

    // Add data before creation
    protected function beforeCreateFill(array &$data): void
    {
        $data['created_by'] = auth()->id();
    }

    // Modify data before creation
    protected function beforeCreate(array &$data): void
    {
        $data['slug'] = Str::slug($data['name']);
    }

    // Actions after creation
    protected function afterCreate(Model $record): void
    {
        event(new ProductCreated($record));
    }

    // Add eager loading to search
    protected function modifySearchQuery(Builder $query): void
    {
        $query->with(['category', 'brand'])
              ->where('is_active', true);
    }

    // Provide user ID for soft delete audit
    protected function getSoftDeleteUserId(): ?int
    {
        return auth()->id();
    }
}
```

## Selective Route Registration

Include or exclude specific actions:

```php
// Only search and read
Router::crud('products', ProductController::class, 'products',
    only: ['search', 'read']
);

// Everything except delete
Router::crud('products', ProductController::class, 'products',
    except: ['delete', 'softDelete']
);
```

## CSV Export Configuration

```php
class ProductController extends CrudController
{
    protected string $csvFileName = 'products.csv';

    protected array $csvColumns = [
        'name' => 'Product Name',
        'category.name' => 'Category',    // Supports dot notation
        'price' => 'Price',
        'created_at' => 'Created At',
    ];
}
```

## API Response Format

All responses follow the [JSend specification](https://github.com/omniti-labs/jsend):

```json
{
    "status": "success",
    "data": { ... },
    "meta": {
        "pagination": {
            "total": 100,
            "per_page": 20,
            "current_page": 1,
            "last_page": 5
        }
    }
}
```

## License

MIT License. See [LICENSE](LICENSE) for details.
