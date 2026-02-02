# Delete Action

The Delete action handles DELETE requests to permanently remove records from the database.

## Route

```
DELETE /{resource}/{id}
```

## Basic Usage

The action automatically handles:
1. Validating the identifier (optionally as UUID)
2. Finding the record
3. Calling lifecycle hooks
4. Deleting the record using Eloquent's `delete()` method
5. Returning a 204 No Content response

## Lifecycle

```
Request → UUID Validation → modifyDeleteQuery() → Find Record → beforeDelete() → Model::delete() → afterDelete() → Response (204)
```

## Hooks

### modifyDeleteQuery(Builder $query)

Modify the query before fetching the record. Use this for authorization.

```php
protected function modifyDeleteQuery(Builder $query): void
{
    // Only allow deleting own records
    $query->where('user_id', auth()->id());

    // Or check permissions
    if (!auth()->user()->isAdmin()) {
        $query->where('team_id', auth()->user()->team_id);
    }

    // Prevent deleting protected records
    $query->where('is_protected', false);
}
```

### beforeDelete(Model $record)

Called right before the delete. Use this for validation or cleanup.

```php
protected function beforeDelete(Model $record): void
{
    // Validate business rules
    if ($record->orders()->exists()) {
        throw new \Exception('Cannot delete product with existing orders');
    }

    // Store data for afterDelete
    $this->deletedProductData = $record->toArray();

    // Clean up related files
    Storage::delete($record->image_path);

    // Detach relationships (if needed)
    $record->tags()->detach();
}
```

### afterDelete(Model $record)

Called after the record has been deleted. Use this for side effects.

```php
protected function afterDelete(Model $record): void
{
    // Dispatch events
    event(new ProductDeleted($this->deletedProductData));

    // Clear cache
    Cache::forget("product:{$record->id}");
    Cache::tags(['products'])->flush();

    // Remove from search index
    SearchIndex::delete($record);

    // Log the deletion
    activity()
        ->performedOn($record)
        ->withProperties($this->deletedProductData)
        ->log('deleted');

    // Notify admins
    Notification::send(
        User::admins()->get(),
        new ProductDeletedNotification($this->deletedProductData)
    );
}
```

## Configuration

In `config/devToolbelt/fast-crud.php`:

```php
'global' => [
    'find_field' => 'id',
    'find_field_is_uuid' => false,
],

'delete' => [
    // Override global settings:
    // 'find_field' => 'uuid',
    // 'find_field_is_uuid' => true,
],
```

## Request Example

```bash
curl -X DELETE http://api.example.com/products/123
```

## Response Example

Success (204 No Content):

```
(empty response body)
```

Error - Not Found (404):

```json
{
    "status": "fail",
    "data": {
        "message": "Record not found"
    }
}
```

Error - Invalid UUID (400):

```json
{
    "status": "fail",
    "data": {
        "message": "Invalid UUID"
    }
}
```

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Events\ProductDeleted;
use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProductController extends CrudController
{
    private array $deletedData = [];

    protected function modelClassName(): string
    {
        return Product::class;
    }

    protected function modifyDeleteQuery(Builder $query): void
    {
        // Only allow store owners to delete their products
        $query->whereHas('store', function ($q) {
            $q->where('owner_id', auth()->id());
        });

        // Don't allow deleting featured products
        $query->where('is_featured', false);
    }

    protected function beforeDelete(Model $record): void
    {
        // Prevent deletion if has orders
        if ($record->orders()->exists()) {
            throw new \DomainException(
                'Cannot delete product with existing orders. Consider soft delete instead.'
            );
        }

        // Store for audit
        $this->deletedData = $record->toArray();

        // Clean up files
        if ($record->image_path) {
            Storage::disk('public')->delete($record->image_path);
        }

        // Clean up relationships
        $record->variants()->delete();
        $record->images()->delete();
    }

    protected function afterDelete(Model $record): void
    {
        event(new ProductDeleted($this->deletedData));

        Cache::tags(['products', "store:{$record->store_id}"])->flush();
    }
}
```

## Delete vs Soft Delete

Use the regular Delete action when:
- Data should be permanently removed
- GDPR/compliance requires hard deletion
- Cleaning up test or invalid data

Use [Soft Delete](soft-delete.md) when:
- You need to keep records for audit trails
- Users might want to restore deleted items
- You need to track who deleted what

## Preventing Accidental Deletion

Add safeguards in `modifyDeleteQuery`:

```php
protected function modifyDeleteQuery(Builder $query): void
{
    // Require confirmation parameter
    if (!request()->boolean('confirm')) {
        throw new \Exception('Deletion requires confirmation');
    }

    // Add time-based restriction
    $query->where('created_at', '<', now()->subDays(30));
}
```

Or validate in `beforeDelete`:

```php
protected function beforeDelete(Model $record): void
{
    // Check for dependent records
    $dependencies = collect([
        'orders' => $record->orders()->count(),
        'reviews' => $record->reviews()->count(),
        'wishlists' => $record->wishlists()->count(),
    ])->filter();

    if ($dependencies->isNotEmpty()) {
        throw new \DomainException(
            'Cannot delete: record has ' . $dependencies->keys()->implode(', ')
        );
    }
}
```
