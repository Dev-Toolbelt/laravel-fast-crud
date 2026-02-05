# Restore Action

The Restore action handles PATCH/PUT requests to restore soft deleted records by clearing the `deleted_at` and `deleted_by` fields.

## Route

```
PATCH /{resource}/{id}/restore
PUT   /{resource}/{id}/restore
```

## Basic Usage

The action automatically handles:
1. Validating the identifier (optionally as UUID)
2. Verifying that the required columns exist on the model
3. Finding only soft deleted records (where `deleted_at` is not null)
4. Calling lifecycle hooks
5. Clearing `deleted_at` and `deleted_by` fields
6. Returning the restored record

## Lifecycle

```
Request → UUID Validation → Column Validation → modifyRestoreQuery() → Find Soft Deleted Record → beforeRestore() → Clear Fields → afterRestore() → Response
```

## Database Requirements

Same as [Soft Delete](soft-delete.md) - your model needs `deleted_at` and `deleted_by` columns.

## Hooks

### modifyRestoreQuery(Builder $query)

Modify the query before fetching the soft deleted record.

```php
protected function modifyRestoreQuery(Builder $query): void
{
    // Only allow restoring own records
    $query->where('user_id', auth()->id());

    // Only allow restoring recently deleted (within 30 days)
    $query->where('deleted_at', '>=', now()->subDays(30));
}
```

### beforeRestore(Model $record)

Called right before the restore.

```php
protected function beforeRestore(Model $record): void
{
    // Validate business rules
    if ($record->deleted_at < now()->subDays(90)) {
        throw new \Exception('Records older than 90 days cannot be restored');
    }

    // Check if a record with same unique field exists
    $exists = Product::query()->where('sku', $record->sku)
        ->whereNull('deleted_at')
        ->exists();

    if ($exists) {
        throw new \Exception('A product with this SKU already exists');
    }

    // Restore related records first
    $record->variants()
        ->whereNotNull('deleted_at')
        ->update([
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
}
```

### afterRestore(Model $record)

Called after the record has been restored.

```php
protected function afterRestore(Model $record): void
{
    // Dispatch events
    event(new ProductRestored($record));

    // Clear cache
    Cache::tags(['products'])->flush();

    // Log the action
    activity()
        ->performedOn($record)
        ->causedBy(auth()->user())
        ->log('restored');

    // Re-index in search
    SearchIndex::update($record);

    // Notify the original creator
    $record->creator->notify(new ProductRestoredNotification($record));
}
```

## Configuration

In `config/devToolbelt/fast-crud.php`:

```php
use DevToolbelt\Enums\Http\HttpStatusCode;

'global' => [
    'find_field' => 'id',
    'find_field_is_uuid' => false,
],

'restore' => [
    'method' => 'toArray',  // Serialization method
    'http_status' => HttpStatusCode::OK->value, // 200
    // Override global:
    // 'find_field' => 'uuid',
],

// Uses same column config as soft_delete
'soft_delete' => [
    'deleted_at_field' => 'deleted_at',
    'deleted_by_field' => 'deleted_by',
],
```

### http_status

The HTTP status code returned on successful restore. Default: `200 OK`.

```php
use DevToolbelt\Enums\Http\HttpStatusCode;

// Return 202 Accepted instead of 200 OK
'restore' => [
    'http_status' => HttpStatusCode::ACCEPTED->value,
],
```

## Request Example

```bash
curl -X PATCH http://api.example.com/products/123/restore

# Or with PUT
curl -X PUT http://api.example.com/products/123/restore
```

## Response Example

Success (200 OK):

```json
{
    "status": "success",
    "data": {
        "id": 123,
        "name": "Restored Product",
        "deleted_at": null,
        "deleted_by": null,
        "updated_at": "2024-01-15T14:30:00Z"
    }
}
```

Error - Column Not Found (uses `global.validation.http_status`, default: 400):

```json
{
    "status": "fail",
    "data": [
        {
            "field": "deleted_at",
            "error": "columnNotFound",
            "message": "Column not found: deleted_at"
        }
    ],
    "meta": []
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

Note: Returns 404 if the record doesn't exist OR if it's not soft deleted.

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Events\ProductRestored;
use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ProductController extends CrudController
{
    protected function modelClassName(): string
    {
        return Product::query()->class;
    }

    protected function modifyRestoreQuery(Builder $query): void
    {
        // Only allow restoring products from user's store
        $query->where('store_id', auth()->user()->store_id);

        // Only allow restoring items deleted within last 30 days
        $query->where('deleted_at', '>=', now()->subDays(30));
    }

    protected function beforeRestore(Model $record): void
    {
        // Check for SKU conflicts
        $conflict = Product::query()->where('sku', $record->sku)
            ->whereNull('deleted_at')
            ->where('id', '!=', $record->id)
            ->first();

        if ($conflict) {
            throw new \DomainException(
                "Cannot restore: SKU '{$record->sku}' is now used by product #{$conflict->id}"
            );
        }

        // Restore variants too
        $record->variants()
            ->whereNotNull('deleted_at')
            ->update([
                'deleted_at' => null,
                'deleted_by' => null,
            ]);
    }

    protected function afterRestore(Model $record): void
    {
        event(new ProductRestored($record));

        Cache::tags(['products', "store:{$record->store_id}"])->flush();

        // Re-add to search index
        $record->searchable();
    }
}
```

## Listing Soft Deleted Records

Use the Search endpoint with the `nn` (not null) operator to list soft deleted records:

```bash
# List all soft deleted products
GET /products?filter[deleted_at][nn]=1

# With pagination
GET /products?filter[deleted_at][nn]=1&perPage=20

# Filter by date range (deleted in last 30 days)
GET /products?filter[deleted_at][nn]=1&filter[deleted_at][gte]=2024-01-01

# Sort by most recently deleted
GET /products?filter[deleted_at][nn]=1&sort=-deleted_at
```

Configure your controller to include soft deleted records in search and eager load the user who deleted:

```php
protected function modifySearchQuery(Builder $query): void
{
    // Include deletedBy relationship when filtering soft deleted
    if (request()->has('filter.deleted_at')) {
        $query->with('deletedBy:id,name');
    }
}
```

## Automatic Cleanup

Consider adding a scheduled task to permanently delete old soft deleted records:

```php
// In app/Console/Kernel.php
$schedule->call(function () {
    Product::query()->query()
        ->whereNotNull('deleted_at')
        ->where('deleted_at', '<', now()->subDays(90))
        ->forceDelete();
})->daily();
```

## Related

- [Soft Delete Action](soft-delete.md) - Soft deleting records
- [Delete Action](delete.md) - Permanent deletion
