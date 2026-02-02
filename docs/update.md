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
Request → UUID Validation → beforeUpdateFill() → Validate Rules → modifyUpdateQuery() → Find Record → beforeUpdate() → Model::update() → afterUpdate() → Response
```

## Validation

Define validation rules by overriding the `updateValidateRules()` method. This uses Laravel's built-in validation system, so all [Laravel validation rules](https://laravel.com/docs/validation#available-validation-rules) are supported.

For partial updates, use `sometimes` to validate only when the field is present:

```php
protected function updateValidateRules(): array
{
    return [
        'name' => ['sometimes', 'string', 'max:255'],
        'email' => ['sometimes', 'email', 'unique:users,email,' . request()->route('id')],
        'price' => ['sometimes', 'numeric', 'min:0'],
    ];
}
```

### When Validation Runs

Validation runs **after** `beforeUpdateFill()` and **before** `modifyUpdateQuery()`. This allows you to transform or add data before validation and ensures validation happens before the record is fetched:

```php
protected function beforeUpdateFill(array &$data): void
{
    // Add audit field before validation
    $data['updated_by'] = auth()->id();
}

protected function updateValidateRules(): array
{
    return [
        'updated_by' => ['required', 'exists:users,id'],
        'name' => ['sometimes', 'string', 'max:255'],
    ];
}
```

### Validation Error Response

Validation errors return a 400 response with detailed information about each failed rule:

```json
{
    "status": "fail",
    "data": [
        {
            "field": "name",
            "error": "max",
            "value": "This is a very long name that exceeds the maximum allowed characters...",
            "message": "The name field must not be greater than 255 characters."
        }
    ],
    "meta": []
}
```

Each error contains:
- `field`: The field name that failed validation
- `error`: The validation rule name (lowercase)
- `value`: The submitted value
- `message`: Laravel's validation message

### Common Validation Patterns

**Unique constraint excluding current record:**
```php
protected function updateValidateRules(): array
{
    $id = request()->route('id');

    return [
        'email' => ['sometimes', 'email', "unique:users,email,{$id}"],
        'slug' => ['sometimes', 'string', "unique:products,slug,{$id}"],
    ];
}
```

**Unique with UUID identifier:**
```php
protected function updateValidateRules(): array
{
    $id = request()->route('id');

    return [
        'email' => ['sometimes', 'email', "unique:users,email,{$id},id"],
        // Or if using external_id as the route parameter:
        // 'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($id, 'external_id')],
    ];
}
```

**Required vs Sometimes:**
```php
protected function updateValidateRules(): array
{
    return [
        // Only validate if field is present (partial update)
        'name' => ['sometimes', 'string', 'max:255'],

        // Always required, even in partial updates
        'status' => ['required', 'in:active,inactive'],

        // Required only if present, with additional rules
        'price' => ['sometimes', 'required', 'numeric', 'min:0'],
    ];
}
```

**Conditional rules:**
```php
protected function updateValidateRules(): array
{
    return [
        'type' => ['sometimes', 'in:physical,digital'],
        'weight' => ['required_if:type,physical', 'numeric', 'min:0'],
        'download_url' => ['required_if:type,digital', 'url'],
    ];
}
```

**Nested/array validation:**
```php
protected function updateValidateRules(): array
{
    return [
        'items' => ['sometimes', 'array', 'min:1'],
        'items.*.product_id' => ['required_with:items', 'exists:products,id'],
        'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
    ];
}
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

    protected function updateValidateRules(): array
    {
        $id = request()->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'category_id' => ['sometimes', 'exists:categories,id'],
            'sku' => ['sometimes', 'string', "unique:products,sku,{$id}"],
        ];
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
