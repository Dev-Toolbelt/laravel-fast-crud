# Read Action

The Read action handles GET requests to retrieve a single record by its identifier.

## Route

```
GET /{resource}/{id}
```

## Basic Usage

The action automatically handles:
1. Validating the identifier (optionally as UUID)
2. Finding the record using the configured `find_field`
3. Returning the record or a 404 error

## Lifecycle

```
Request → UUID Validation → modifyReadQuery() → Find Record → afterRead() → Response
```

## Hooks

### modifyReadQuery(Builder $query)

Modify the query before fetching the record. Use this for eager loading or additional conditions.

```php
protected function modifyReadQuery(Builder $query): void
{
    // Eager load relationships
    $query->with(['category', 'brand', 'images']);

    // Add conditions
    $query->where('is_active', true);

    // Scope to current user
    $query->where('user_id', auth()->id());
}
```

### afterRead(Model $record)

Called after the record has been fetched. Use this for logging, analytics, or side effects.

```php
protected function afterRead(Model $record): void
{
    // Log view
    activity()
        ->performedOn($record)
        ->log('viewed');

    // Increment view counter
    $record->increment('views');

    // Track analytics
    Analytics::track('product_viewed', [
        'product_id' => $record->id,
        'user_id' => auth()->id(),
    ]);
}
```

## Configuration

In `config/devToolbelt/fast-crud.php`:

```php
'global' => [
    'find_field' => 'id',           // Field to find records by
    'find_field_is_uuid' => false,  // Validate as UUID
],

'read' => [
    'method' => 'toArray',          // Serialization method
    // Override global settings for this action:
    // 'find_field' => 'uuid',
    // 'find_field_is_uuid' => true,
],
```

### find_field

The database column used to find records. Default: `id`.

```php
// Find by UUID column
'read' => [
    'find_field' => 'external_id',
    'find_field_is_uuid' => true,
],
```

### find_field_is_uuid

When `true`, validates that the identifier is a valid UUID before querying.

### method

The model method used to serialize the response.

## Request Example

```bash
# By ID
curl http://api.example.com/products/123

# By UUID (if configured)
curl http://api.example.com/products/550e8400-e29b-41d4-a716-446655440000
```

## Response Example

Success (200 OK):

```json
{
    "status": "success",
    "data": {
        "id": 123,
        "name": "Product Name",
        "price": 99.99,
        "category": {
            "id": 1,
            "name": "Electronics"
        },
        "created_at": "2024-01-15T10:30:00Z"
    }
}
```

Error - Not Found (404):

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

Error - Invalid UUID (400):

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

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductController extends CrudController
{
    protected function modelClassName(): string
    {
        return Product::query()->class;
    }

    protected function modifyReadQuery(Builder $query): void
    {
        $query->with([
            'category',
            'brand',
            'images',
            'reviews' => fn($q) => $q->latest()->limit(5),
        ]);

        // Only show active products
        $query->where('is_active', true);
    }

    protected function afterRead(Model $record): void
    {
        // Log the view for analytics
        $record->recordView(auth()->user());
    }
}
```

## Using Custom Find Field

To find records by a different field (e.g., slug):

```php
// config/devToolbelt/fast-crud.php
'read' => [
    'find_field' => 'slug',
    'find_field_is_uuid' => false,
],
```

Now you can access: `GET /products/my-product-slug`
