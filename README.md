# Laravel Fast CRUD

A powerful Laravel package for rapid CRUD API scaffolding. Build complete RESTful APIs in minutes with minimal boilerplate, following best practices and the [JSend specification](https://github.com/omniti-labs/jsend).

## Features

- Works exclusively with Laravel 11+
- 9 pre-built actions (Create, Read, Update, Delete, Soft Delete, Restore, Search, Options, Export CSV)
- 14+ search operators for flexible filtering (eq, like, between, in, json, etc.)
- Automatic route registration with permission middleware support
- Hook system for customization without overriding entire methods
- Global and per-action configuration
- Soft delete with audit trail (who deleted and when)
- CSV export with custom column mapping and dot notation support
- Consistent API responses following JSend specification

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

That's it! You now have a complete CRUD API.

## Available Actions

| Action | Method | Route | Description |
|--------|--------|-------|-------------|
| Search | `GET` | `/products` | List with filtering, sorting, pagination |
| Create | `POST` | `/products` | Create a new record |
| Read | `GET` | `/products/{id}` | Get a single record |
| Update | `PUT/PATCH` | `/products/{id}` | Update a record |
| Delete | `DELETE` | `/products/{id}` | Permanently delete a record |
| Soft Delete | `DELETE` | `/products/{id}/soft` | Soft delete with audit |
| Restore | `PATCH/PUT` | `/products/{id}/restore` | Restore a soft deleted record |
| Options | `GET` | `/products/options` | Label-value pairs for HTML selects |
| Export CSV | `GET` | `/products/export-csv` | Export filtered data to CSV |

## Search Operators

Filter your data with powerful operators via query string:

```
GET /products?filter[name][like]=Samsung&filter[price][gte]=100&filter[status][in]=active,pending
```

| Operator | Description | Example |
|----------|-------------|---------|
| `eq` | Equals | `filter[status][eq]=active` |
| `neq` | Not equals | `filter[status][neq]=deleted` |
| `like` | Contains (ILIKE %value%) | `filter[name][like]=phone` |
| `in` | In list | `filter[status][in]=a,b,c` |
| `nin` | Not in list | `filter[status][nin]=x,y` |
| `gt` | Greater than | `filter[price][gt]=100` |
| `gte` | Greater than or equal | `filter[price][gte]=100` |
| `lt` | Less than | `filter[price][lt]=500` |
| `lte` | Less than or equal | `filter[price][lte]=500` |
| `btw` | Between (dates or numbers) | `filter[created_at][btw]=2024-01-01,2024-12-31` |
| `gtn` | Greater than or NULL | `filter[stock][gtn]=10` |
| `ltn` | Less than or NULL | `filter[stock][ltn]=5` |
| `nn` | Not null | `filter[deleted_at][nn]=1` |
| `json` | JSON contains | `filter[tags][json]=electronics` |

## Sorting

Sort by one or multiple fields (prefix with `-` for descending):

```
GET /products?sort=name              # ASC by name
GET /products?sort=-created_at       # DESC by created_at
GET /products?sort=category,-price   # ASC category, DESC price
```

## Pagination

```
GET /products?perPage=20             # 20 items per page (default: 40)
GET /products?skipPagination=true    # Return all records
```

## Available Hooks

Customize behavior by overriding hooks in your controller:

| Action | Available Hooks |
|--------|-----------------|
| Create | `beforeCreateFill()`, `beforeCreate()`, `afterCreate()` |
| Read | `modifyReadQuery()`, `afterRead()` |
| Update | `modifyUpdateQuery()`, `beforeUpdateFill()`, `beforeUpdate()`, `afterUpdate()` |
| Delete | `modifyDeleteQuery()`, `beforeDelete()`, `afterDelete()` |
| Soft Delete | `modifySoftDeleteQuery()`, `beforeSoftDelete()`, `afterSoftDelete()`, `getSoftDeleteUserId()` |
| Restore | `modifyRestoreQuery()`, `beforeRestore()`, `afterRestore()` |
| Search | `modifySearchQuery()`, `afterSearch()` |
| Options | `modifyOptionsQuery()`, `afterOptions()` |
| Export CSV | `modifyExportCsvQuery()` |

### Hook Examples

```php
class ProductController extends CrudController
{
    protected function modelClassName(): string
    {
        return Product::class;
    }

    // Add data before filling the model
    protected function beforeCreateFill(array &$data): void
    {
        $data['created_by'] = auth()->id();
    }

    // Modify data before saving
    protected function beforeCreate(array &$data): void
    {
        $data['slug'] = Str::slug($data['name']);
    }

    // Dispatch events after creation
    protected function afterCreate(Model $record): void
    {
        event(new ProductCreated($record));
    }

    // Add eager loading and scopes to search
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

## Configuration

```php
// config/devToolbelt/fast-crud.php

return [
    // Global settings (applied to all actions)
    'global' => [
        'find_field' => 'id',           // Field to find records by
        'find_field_is_uuid' => false,  // Validate as UUID before querying
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
    'options' => ['default_value' => 'id'],
    'export_csv' => ['method' => 'toArray'],
];
```

## Selective Route Registration

Include or exclude specific actions:

```php
// Only search and read (read-only API)
Router::crud('products', ProductController::class, 'products',
    only: ['search', 'read']
);

// Everything except permanent delete
Router::crud('products', ProductController::class, 'products',
    except: ['delete']
);
```

## CSV Export

Configure CSV export with custom columns and nested relationships:

```php
class ProductController extends CrudController
{
    protected string $csvFileName = 'products.csv';

    protected array $csvColumns = [
        'name' => 'Product Name',
        'category.name' => 'Category',    // Dot notation for relationships
        'price' => 'Price',
        'created_at' => 'Created At',
    ];
}
```

## API Response Format

All responses follow the [JSend specification](https://github.com/omniti-labs/jsend):

**Success Response:**
```json
{
    "status": "success",
    "data": { ... }
}
```

**Success with Pagination:**
```json
{
    "status": "success",
    "data": [ ... ],
    "meta": {
        "pagination": {
            "current": 1,
            "perPage": 40,
            "pagesCount": 5,
            "count": 200
        }
    }
}
```

**Fail Response (client error):**
```json
{
    "status": "fail",
    "data": [
        {
            "field": "id",
            "error": "recordNotFound",
            "message": "The record was not found with the given id"
        }
    ],
    "meta": []
}
```

**Empty Payload Error:**
```json
{
    "status": "fail",
    "data": [
        {
            "error": "emptyPayload",
            "message": "It was send a empty payload"
        }
    ],
    "meta": []
}
```

**Invalid UUID Error:**
```json
{
    "status": "fail",
    "data": [
        {
            "field": "id",
            "error": "invalidUuidFormat",
            "message": "The provided uuid format is invalid"
        }
    ],
    "meta": []
}
```

## Documentation

Detailed documentation for each action:

- [Create Action](docs/create.md)
- [Read Action](docs/read.md)
- [Update Action](docs/update.md)
- [Delete Action](docs/delete.md)
- [Soft Delete Action](docs/soft-delete.md)
- [Restore Action](docs/restore.md)
- [Search Action](docs/search.md)
- [Options Action](docs/options.md)
- [Export CSV Action](docs/export-csv.md)

## License

MIT License. See [LICENSE](LICENSE) for details.
