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
Request → beforeCreateFill() → beforeCreate() → Model::create() → afterCreate() → Response
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
    if (Product::where('sku', $data['sku'])->exists()) {
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
    "data": {
        "message": "Empty payload"
    }
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
        return Product::class;
    }

    protected function beforeCreateFill(array &$data): void
    {
        $data['created_by'] = auth()->id();
    }

    protected function beforeCreate(array &$data): void
    {
        $data['slug'] = Str::slug($data['name']);
        $data['sku'] = $this->generateSku($data);
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
