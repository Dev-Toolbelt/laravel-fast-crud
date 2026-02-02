# Router

The Router class provides a convenient method to register all RESTful routes for a CRUD controller with automatic permission middleware assignment.

## Basic Usage

```php
use App\Http\Controllers\ProductController;
use DevToolbelt\LaravelFastCrud\Router;

Router::crud('products', ProductController::class, 'products');
```

This single line registers all CRUD routes for the `ProductController`.

## Method Signature

```php
Router::crud(
    string $uri,           // Base URI for the resource
    string $controllerName, // Fully qualified controller class name
    string $moduleName,    // Module name for permission middleware
    array $except = [],    // Actions to exclude
    array $only = []       // Actions to include (if set, only these are registered)
): void
```

## Generated Routes

The `crud()` method generates the following routes:

| Method | URI | Controller Method | Permission |
|--------|-----|-------------------|------------|
| `GET` | `/{uri}` | `search()` | `{module}.access.search` |
| `GET` | `/{uri}/options` | `options()` | `{module}.access.search` |
| `POST` | `/{uri}` | `create()` | `{module}.access.create` |
| `GET` | `/{uri}/export-csv` | `exportCsv()` | `{module}.access.exportCsv` |
| `GET` | `/{uri}/{id}` | `read()` | `{module}.access.view` |
| `PUT` | `/{uri}/{id}` | `update()` | `{module}.access.update` |
| `PATCH` | `/{uri}/{id}` | `update()` | `{module}.access.update` |
| `POST` | `/{uri}/{id}` | `update()` | `{module}.access.update` |
| `DELETE` | `/{uri}/{id}` | `delete()` | `{module}.access.delete` |
| `DELETE` | `/{uri}/{id}/soft` | `softDelete()` | `{module}.access.delete` |
| `PATCH` | `/{uri}/{id}/restore` | `restore()` | `{module}.access.restore` |
| `PUT` | `/{uri}/{id}/restore` | `restore()` | `{module}.access.restore` |

## Route Parameters

The `{id}` parameter accepts UUID format by default. This is defined in the route pattern as `{id:uuid}`.

## Permission Middleware

Each route is automatically assigned a permission middleware using the pattern:

```
can:{moduleName}.access.{permission}
```

For example, with `moduleName = 'products'`:

- Search route: `can:products.access.search`
- Create route: `can:products.access.create`
- Read route: `can:products.access.view`
- Update route: `can:products.access.update`
- Delete route: `can:products.access.delete`
- Export CSV route: `can:products.access.exportCsv`
- Restore route: `can:products.access.restore`

## Filtering Routes

### Using `only`

Register only specific actions:

```php
// Only register search and read routes (read-only API)
Router::crud('products', ProductController::class, 'products',
    only: ['search', 'read']
);
```

This generates only:
- `GET /products` -> `search()`
- `GET /products/{id}` -> `read()`

### Using `except`

Exclude specific actions:

```php
// Register everything except permanent delete and export
Router::crud('products', ProductController::class, 'products',
    except: ['delete', 'exportCsv']
);
```

### Available Action Names

Use these names with `only` and `except`:

| Action Name | Description |
|-------------|-------------|
| `search` | List/search records |
| `options` | Get label-value pairs for selects |
| `create` | Create new record |
| `read` | Get single record |
| `update` | Update existing record |
| `delete` | Permanently delete record |
| `softDelete` | Soft delete record |
| `restore` | Restore soft deleted record |
| `exportCsv` | Export records to CSV |

## Examples

### Basic Registration

```php
// routes/api.php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use DevToolbelt\LaravelFastCrud\Router;

// Full CRUD for products
Router::crud('products', ProductController::class, 'products');

// Full CRUD for categories
Router::crud('categories', CategoryController::class, 'categories');

// Full CRUD for orders
Router::crud('orders', OrderController::class, 'orders');
```

### Read-Only API

```php
// Public catalog - no create, update, or delete
Router::crud('catalog', CatalogController::class, 'catalog',
    only: ['search', 'read', 'options']
);
```

### No Permanent Delete

```php
// Use soft delete only, no permanent deletion
Router::crud('users', UserController::class, 'users',
    except: ['delete']
);
```

### Minimal API

```php
// Only basic CRUD operations
Router::crud('settings', SettingsController::class, 'settings',
    except: ['softDelete', 'restore', 'exportCsv', 'options']
);
```

### API Versioning

```php
// API v1
Route::prefix('api/v1')->group(function () {
    Router::crud('products', ProductController::class, 'products');
});

// API v2 with different controller
Route::prefix('api/v2')->group(function () {
    Router::crud('products', ProductV2Controller::class, 'products');
});
```

### With Route Groups

```php
Route::middleware(['auth:sanctum'])->group(function () {
    Router::crud('products', ProductController::class, 'products');
    Router::crud('orders', OrderController::class, 'orders');
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Router::crud('users', UserController::class, 'users');
});
```

## Method Existence Check

The Router automatically checks if the controller method exists before registering the route. If a method doesn't exist in the controller, the route is skipped silently.

```php
class MinimalController extends CrudController
{
    use Search; // Only has search() method
    use Read;   // Only has read() method

    protected function modelClassName(): string
    {
        return Product::class;
    }
}

// Only search and read routes will be registered
// Other routes are skipped because methods don't exist
Router::crud('minimal', MinimalController::class, 'minimal');
```

## Integration with Laravel Gates

The permission middleware uses Laravel's authorization system. Define gates or policies to control access:

```php
// app/Providers/AuthServiceProvider.php

use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // Define gates for products module
    Gate::define('products.access.search', function ($user) {
        return true; // Everyone can search
    });

    Gate::define('products.access.create', function ($user) {
        return $user->hasRole('editor');
    });

    Gate::define('products.access.delete', function ($user) {
        return $user->hasRole('admin');
    });
}
```

Or use a policy:

```php
// app/Policies/ProductPolicy.php

class ProductPolicy
{
    public function search(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('create-products');
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function update(User $user): bool
    {
        return $user->can('edit-products');
    }

    public function delete(User $user): bool
    {
        return $user->can('delete-products');
    }
}
```

## Listing Registered Routes

Use Laravel's Artisan command to see all registered routes:

```bash
php artisan route:list --path=products
```

Output:
```
GET|HEAD   products .................. ProductController@search
POST       products .................. ProductController@create
GET|HEAD   products/options .......... ProductController@options
GET|HEAD   products/export-csv ....... ProductController@exportCsv
GET|HEAD   products/{id} ............. ProductController@read
PUT        products/{id} ............. ProductController@update
PATCH      products/{id} ............. ProductController@update
POST       products/{id} ............. ProductController@update
DELETE     products/{id} ............. ProductController@delete
DELETE     products/{id}/soft ........ ProductController@softDelete
PATCH      products/{id}/restore ..... ProductController@restore
PUT        products/{id}/restore ..... ProductController@restore
```
