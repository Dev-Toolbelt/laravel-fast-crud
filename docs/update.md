# Update Action

The Update action handles PUT, PATCH, and POST requests to update existing records.

## Route

```
PUT    /{resource}/{id}
PATCH  /{resource}/{id}
POST   /{resource}/{id}
```

## Basic Usage

The action automatically handles:
1. Validating the identifier (optionally as UUID)
2. Finding the record
3. Calling lifecycle hooks
4. Updating the record using Eloquent's `update()` method
5. Returning the updated record

## Lifecycle

```
Request → UUID Validation → modifyUpdateQuery() → Find Record → beforeUpdateFill() → beforeUpdate() → Model::update() → afterUpdate() → Response
```

## Hooks

### modifyUpdateQuery(Builder $query)

Modify the query before fetching the record. Use this for authorization or scoping.

```php
protected function modifyUpdateQuery(Builder $query): void
{
    // Only allow updating own records
    $query->where('user_id', auth()->id());

    // Or check ownership via relationship
    $query->whereHas('team', function ($q) {
        $q->whereHas('members', function ($q) {
            $q->where('user_id', auth()->id());
        });
    });
}
```

### beforeUpdateFill(array &$data)

Called first, before processing. Use this to add or transform data.

```php
protected function beforeUpdateFill(array &$data): void
{
    // Add audit fields
    $data['updated_by'] = auth()->id();

    // Remove fields that shouldn't be updated
    unset($data['created_by']);
    unset($data['created_at']);

    // Transform data
    if (isset($data['email'])) {
        $data['email'] = strtolower($data['email']);
    }
}
```

### beforeUpdate(Model $record, array &$data)

Called right before the update. You have access to both the existing record and the new data.

```php
protected function beforeUpdate(Model $record, array &$data): void
{
    // Regenerate slug if name changed
    if (isset($data['name']) && $data['name'] !== $record->name) {
        $data['slug'] = Str::slug($data['name']);
    }

    // Validate business rules
    if (isset($data['price']) && $data['price'] < $record->minimum_price) {
        throw new ValidationException('Price cannot be below minimum');
    }

    // Track changes
    $this->originalData = $record->toArray();
}
```

### afterUpdate(Model $record)

Called after the record has been updated. Use this for side effects.

```php
protected function afterUpdate(Model $record): void
{
    // Dispatch events
    event(new ProductUpdated($record, $this->originalData));

    // Clear cache
    Cache::forget("product:{$record->id}");
    Cache::tags(['products'])->flush();

    // Sync to external services
    SearchIndex::update($record);

    // Notify watchers
    $record->watchers->each->notify(new ProductUpdatedNotification($record));
}
```

## Configuration

In `config/devToolbelt/fast-crud.php`:

```php
'global' => [
    'find_field' => 'id',
    'find_field_is_uuid' => false,
],

'update' => [
    'method' => 'toArray',
    // Override global settings:
    // 'find_field' => 'uuid',
],
```

## Request Example

```bash
# Full update with PUT
curl -X PUT http://api.example.com/products/123 \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Product",
    "price": 149.99
  }'

# Partial update with PATCH
curl -X PATCH http://api.example.com/products/123 \
  -H "Content-Type: application/json" \
  -d '{
    "price": 149.99
  }'
```

## Response Example

Success (200 OK):

```json
{
    "status": "success",
    "data": {
        "id": 123,
        "name": "Updated Product",
        "price": 149.99,
        "updated_at": "2024-01-15T14:30:00Z"
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

Error - Empty Payload (400):

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

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Events\ProductUpdated;
use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductController extends CrudController
{
    private array $originalData = [];

    protected function modelClassName(): string
    {
        return Product::class;
    }

    protected function modifyUpdateQuery(Builder $query): void
    {
        // Only allow updating products in user's store
        $query->where('store_id', auth()->user()->store_id);
    }

    protected function beforeUpdateFill(array &$data): void
    {
        $data['updated_by'] = auth()->id();

        // Prevent changing certain fields
        unset($data['sku'], $data['created_by']);
    }

    protected function beforeUpdate(Model $record, array &$data): void
    {
        // Store original for comparison
        $this->originalData = $record->toArray();

        // Update slug if name changed
        if (isset($data['name']) && $data['name'] !== $record->name) {
            $data['slug'] = Str::slug($data['name']);
        }
    }

    protected function afterUpdate(Model $record): void
    {
        // Dispatch event with changes
        event(new ProductUpdated($record, $this->originalData));

        // Clear caches
        Cache::forget("product:{$record->id}");
        Cache::tags(['products', "category:{$record->category_id}"])->flush();
    }
}
```

## Tracking Changes

To track what changed during an update:

```php
protected function beforeUpdate(Model $record, array &$data): void
{
    $this->changes = [];

    foreach ($data as $key => $value) {
        if ($record->$key !== $value) {
            $this->changes[$key] = [
                'old' => $record->$key,
                'new' => $value,
            ];
        }
    }
}

protected function afterUpdate(Model $record): void
{
    if (!empty($this->changes)) {
        AuditLog::create([
            'model_type' => get_class($record),
            'model_id' => $record->id,
            'user_id' => auth()->id(),
            'changes' => $this->changes,
        ]);
    }
}
```
