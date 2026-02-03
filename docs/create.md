# Create Action

The Create action handles POST requests to create new records in the database.

## Route

```
POST /{resource}
```

## Basic Usage

The action automatically handles:
1. Validating that the request body is not empty
2. Calling lifecycle hooks
3. Creating the record using Eloquent's `create()` method
4. Returning a 201 Created response with the new record

## Lifecycle

```
Request → beforeCreateFill() → Validate Rules → beforeCreate() → Model::create() → afterCreate() → Response
```

## Validation

Define validation rules by overriding the `createValidateRules()` method. This uses Laravel's built-in validation system, so all [Laravel validation rules](https://laravel.com/docs/validation#available-validation-rules) are supported.

```php
protected function createValidateRules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
        'price' => ['required', 'numeric', 'min:0'],
    ];
}
```

### When Validation Runs

Validation runs **after** `beforeCreateFill()` and **before** `beforeCreate()`. This allows you to transform or add data in `beforeCreateFill()` before validation:

```php
protected function beforeCreateFill(array &$data): void
{
    // Add company_id before validation
    $data['company_id'] = auth()->user()->company_id;
}

protected function createValidateRules(): array
{
    return [
        'company_id' => ['required', 'exists:companies,id'],
        'name' => ['required', 'string', 'max:255'],
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
            "error": "required",
            "value": null,
            "message": "The name field is required."
        },
        {
            "field": "email",
            "error": "email",
            "value": "invalid-email",
            "message": "The email field must be a valid email address."
        }
    ],
    "meta": []
}
```

Each error contains:
- `field`: The field name that failed validation
- `error`: The validation rule name (lowercase)
- `value`: The submitted value (or null if not provided)
- `message`: Laravel's validation message

### Common Validation Patterns

**Unique constraint:**
```php
protected function createValidateRules(): array
{
    return [
        'email' => ['required', 'email', 'unique:users,email'],
        'slug' => ['required', 'string', 'unique:products,slug'],
    ];
}
```

**Conditional rules:**
```php
protected function createValidateRules(): array
{
    return [
        'type' => ['required', 'in:physical,digital'],
        'weight' => ['required_if:type,physical', 'numeric', 'min:0'],
        'download_url' => ['required_if:type,digital', 'url'],
    ];
}
```

**Nested/array validation:**
```php
protected function createValidateRules(): array
{
    return [
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'exists:products,id'],
        'items.*.quantity' => ['required', 'integer', 'min:1'],
    ];
}
```

**With custom messages (using Laravel's standard approach):**
```php
protected function createValidateRules(): array
{
    return [
        'email' => ['required', 'email', 'unique:users'],
    ];
}

// In your AppServiceProvider or a dedicated ValidationServiceProvider:
Validator::replacer('unique', function ($message, $attribute, $rule, $parameters) {
    return "This {$attribute} is already taken.";
});
```

## Hooks

### beforeCreateFill(array &$data)

Called first, before any processing. Use this to add or transform data.

```php
protected function beforeCreateFill(array &$data): void
{
    // Add audit fields
    $data['created_by'] = auth()->id();
    $data['ip_address'] = request()->ip();

    // Transform data
    $data['email'] = strtolower($data['email']);
}
```

### beforeCreate(array &$data)

Called after `beforeCreateFill`, right before the record is created. Use this for final validations or modifications.

```php
protected function beforeCreate(array &$data): void
{
    // Generate slug from name
    $data['slug'] = Str::slug($data['name']);

    // Set default values
    $data['status'] = $data['status'] ?? 'pending';

    // Custom validation
    if (Product::query()->where('sku', $data['sku'])->exists()) {
        throw new ValidationException('SKU already exists');
    }
}
```

### afterCreate(Model $record)

Called after the record has been successfully created. Use this for side effects.

```php
protected function afterCreate(Model $record): void
{
    // Dispatch events
    event(new ProductCreated($record));

    // Create related records
    $record->inventory()->create([
        'quantity' => 0,
        'warehouse_id' => 1,
    ]);

    // Send notifications
    $record->user->notify(new ProductCreatedNotification($record));

    // Clear cache
    Cache::tags(['products'])->flush();
}
```

## Configuration

In `config/devToolbelt/fast-crud.php`:

```php
'create' => [
    'method' => 'toArray',  // Serialization method for response
],
```

### method

The model method used to serialize the response. Default: `toArray`.

```php
// Use a custom method
'create' => [
    'method' => 'toApiResponse',
],
```

## Request Example

```bash
curl -X POST http://api.example.com/products \
  -H "Content-Type: application/json" \
  -d '{
    "name": "New Product",
    "price": 99.99,
    "category_id": 1
  }'
```

## Response Example

Success (201 Created):

```json
{
    "status": "success",
    "data": {
        "id": 123,
        "name": "New Product",
        "price": 99.99,
        "category_id": 1,
        "created_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:30:00Z"
    }
}
```

Error - Empty Payload (400 Bad Request):

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

use App\Events\ProductCreated;
use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductController extends CrudController
{
    protected function modelClassName(): string
    {
        return Product::query()->class;
    }

    protected function createValidateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'sku' => ['sometimes', 'string', 'unique:products,sku'],
        ];
    }

    protected function beforeCreateFill(array &$data): void
    {
        $data['created_by'] = auth()->id();
    }

    protected function beforeCreate(array &$data): void
    {
        $data['slug'] = Str::slug($data['name']);

        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku($data);
        }
    }

    protected function afterCreate(Model $record): void
    {
        event(new ProductCreated($record));
    }

    private function generateSku(array $data): string
    {
        return strtoupper(substr($data['name'], 0, 3)) . '-' . time();
    }
}
```
