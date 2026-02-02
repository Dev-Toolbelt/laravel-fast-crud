# Soft Delete Action

The Soft Delete action handles DELETE requests to soft delete records by setting `deleted_at` and `deleted_by` fields instead of permanently removing them.

## Route

```
DELETE /{resource}/{id}/soft
```

## Basic Usage

The action automatically handles:
1. Validating the identifier (optionally as UUID)
2. Verifying that the required columns exist on the model
3. Finding the record
4. Calling lifecycle hooks
5. Updating `deleted_at` with current timestamp
6. Updating `deleted_by` with the user ID
7. Returning a 204 No Content response

## Lifecycle

```
Request → UUID Validation → Column Validation → modifySoftDeleteQuery() → Find Record → beforeSoftDelete() → Update Fields → afterSoftDelete() → Response (204)
```

## Database Requirements

Your model's table must have these columns (configurable):

```php
Schema::table('products', function (Blueprint $table) {
    $table->timestamp('deleted_at')->nullable();
    $table->unsignedBigInteger('deleted_by')->nullable();

    $table->foreign('deleted_by')->references('id')->on('users');
});
```

And the model must have these in `$fillable`:

```php
protected $fillable = [
    // ... other fields
    'deleted_at',
    'deleted_by',
];
```

## Hooks

### modifySoftDeleteQuery(Builder $query)

Modify the query before fetching the record.

```php
protected function modifySoftDeleteQuery(Builder $query): void
{
    // Only allow soft deleting own records
    $query->where('user_id', auth()->id());

    // Don't allow soft deleting already soft deleted records
    $query->whereNull('deleted_at');
}
```

### beforeSoftDelete(Model $record)

Called right before the soft delete.

```php
protected function beforeSoftDelete(Model $record): void
{
    // Validate business rules
    if ($record->is_default) {
        throw new \Exception('Cannot delete the default item');
    }

    // Store data for audit
    $this->softDeletedData = $record->toArray();

    // Soft delete related records
    $record->variants()->update([
        'deleted_at' => now(),
        'deleted_by' => auth()->id(),
    ]);
}
```

### afterSoftDelete(Model $record)

Called after the record has been soft deleted.

```php
protected function afterSoftDelete(Model $record): void
{
    // Dispatch events
    event(new ProductSoftDeleted($record));

    // Clear cache
    Cache::forget("product:{$record->id}");

    // Log the action
    activity()
        ->performedOn($record)
        ->causedBy(auth()->user())
        ->log('soft deleted');

    // Notify admins
    Notification::send(
        User::admins()->get(),
        new ProductSoftDeletedNotification($record)
    );
}
```

### getSoftDeleteUserId()

Returns the user ID to store in the `deleted_by` field. Override to customize.

```php
protected function getSoftDeleteUserId(): int|string|null
{
    return auth()->id();
}

// Or for API tokens:
protected function getSoftDeleteUserId(): int|string|null
{
    return auth()->user()?->id ?? request()->header('X-API-User-ID');
}
```

## Configuration

In `config/devToolbelt/fast-crud.php`:

```php
'global' => [
    'find_field' => 'id',
    'find_field_is_uuid' => false,
],

'soft_delete' => [
    'deleted_at_field' => 'deleted_at',  // Timestamp column
    'deleted_by_field' => 'deleted_by',  // User ID column
    // Override global:
    // 'find_field' => 'uuid',
],
```

### Custom Column Names

```php
'soft_delete' => [
    'deleted_at_field' => 'archived_at',
    'deleted_by_field' => 'archived_by',
],
```

## Request Example

```bash
curl -X DELETE http://api.example.com/products/123/soft
```

## Response Example

Success (204 No Content):

```
(empty response body)
```

Error - Column Not Found (400):

```json
{
    "status": "fail",
    "data": [
        {
            "field": "deleted_by",
            "error": "columnNotFound",
            "message": "Column not found: deleted_by"
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

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Events\ProductSoftDeleted;
use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ProductController extends CrudController
{
    protected function modelClassName(): string
    {
        return Product::class;
    }

    protected function modifySoftDeleteQuery(Builder $query): void
    {
        // Only allow soft deleting products from user's store
        $query->where('store_id', auth()->user()->store_id);

        // Ensure not already soft deleted
        $query->whereNull('deleted_at');
    }

    protected function beforeSoftDelete(Model $record): void
    {
        // Can't soft delete products with pending orders
        if ($record->orders()->where('status', 'pending')->exists()) {
            throw new \DomainException(
                'Cannot delete product with pending orders'
            );
        }

        // Cascade soft delete to variants
        $record->variants()->update([
            'deleted_at' => now(),
            'deleted_by' => $this->getSoftDeleteUserId(),
        ]);
    }

    protected function afterSoftDelete(Model $record): void
    {
        event(new ProductSoftDeleted($record));

        Cache::tags(['products', "store:{$record->store_id}"])->flush();
    }

    protected function getSoftDeleteUserId(): ?int
    {
        return auth()->id();
    }
}
```

## Filtering Out Soft Deleted Records

In your Search queries, add a scope:

```php
protected function modifySearchQuery(Builder $query): void
{
    // Only show non-deleted records
    $query->whereNull('deleted_at');
}
```

Or use a global scope on your model:

```php
// In Product model
protected static function booted(): void
{
    static::addGlobalScope('not_soft_deleted', function (Builder $builder) {
        $builder->whereNull('deleted_at');
    });
}
```

## Difference from Laravel's SoftDeletes

This implementation is independent of Laravel's `SoftDeletes` trait:

| Feature | Laravel SoftDeletes | Fast CRUD Soft Delete |
|---------|--------------------|-----------------------|
| Trait required | Yes | No |
| `deleted_by` tracking | No | Yes |
| Auto-filtering | Yes (global scope) | Manual |
| Restore method | `restore()` | Custom update |
| Column names | Fixed | Configurable |

## Related

- [Restore Action](restore.md) - Restoring soft deleted records
- [Delete Action](delete.md) - Permanent deletion
