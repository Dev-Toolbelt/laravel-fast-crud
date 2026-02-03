# Search Action

The Search action handles GET requests to retrieve a paginated, filtered, and sorted list of records.

## Route

```
GET /{resource}
```

## Basic Usage

The action automatically handles:
1. Filtering via query parameters
2. Sorting by one or multiple fields
3. Pagination
4. Calling lifecycle hooks
5. Returning records with pagination metadata

## Query Parameters

| Parameter | Description | Example |
|-----------|-------------|---------|
| `filter[field][operator]` | Filter by field | `filter[status][eq]=active` |
| `sort` | Sort by fields | `sort=-created_at,name` |
| `perPage` | Items per page | `perPage=20` |
| `skipPagination` | Return all records | `skipPagination=true` |

> **📖 Complete Guide:** For a comprehensive reference with SQL equivalents and best practices, see the [API Query String Guide](search-query-string-guide.md).

## Filtering

### Available Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `eq` | Equals | `filter[status][eq]=active` |
| `neq` | Not equals | `filter[status][neq]=deleted` |
| `like` | Contains (LIKE %value%) | `filter[name][like]=phone` |
| `in` | In list | `filter[status][in]=active,pending` |
| `nin` | Not in list | `filter[status][nin]=deleted,archived` |
| `gt` | Greater than | `filter[price][gt]=100` |
| `gte` | Greater than or equal | `filter[price][gte]=100` |
| `lt` | Less than | `filter[price][lt]=500` |
| `lte` | Less than or equal | `filter[price][lte]=500` |
| `gtn` | Greater than (nullable) | `filter[stock][gtn]=0` |
| `ltn` | Less than (nullable) | `filter[stock][ltn]=10` |
| `btw` | Between | `filter[price][btw]=100,500` |
| `nn` | Not null | `filter[deleted_at][nn]=1` |
| `json` | JSON contains | `filter[tags][json]=featured` |

### Filter Examples

```bash
# Single filter
GET /products?filter[status][eq]=active

# Multiple filters (AND)
GET /products?filter[status][eq]=active&filter[price][gte]=100

# LIKE search
GET /products?filter[name][like]=samsung

# In list
GET /products?filter[category_id][in]=1,2,3

# Between (range)
GET /products?filter[price][btw]=100,500

# Not null
GET /products?filter[featured_at][nn]=1

# JSON field contains
GET /products?filter[metadata][json]=premium
```

## Sorting

Sort by one or multiple fields. Prefix with `-` for descending order.

```bash
# Single field ascending
GET /products?sort=name

# Single field descending
GET /products?sort=-created_at

# Multiple fields
GET /products?sort=category_id,-price,name
```

## Pagination

```bash
# Custom per page
GET /products?perPage=50

# Skip pagination (get all)
GET /products?skipPagination=true
```

## Lifecycle

```
Request → modifySearchQuery() → Apply Filters → Apply Sorting → Paginate → afterSearch() → Response
```

## Hooks

### modifySearchQuery(Builder $query)

Modify the query before filters and sorting are applied. Use this for base conditions and eager loading.

```php
protected function modifySearchQuery(Builder $query): void
{
    // Eager load relationships
    $query->with(['category', 'brand', 'images']);

    // Add base conditions
    $query->where('is_active', true);

    // Scope to current user
    $query->where('store_id', auth()->user()->store_id);

    // Exclude soft deleted
    $query->whereNull('deleted_at');

    // Select specific columns
    $query->select(['id', 'name', 'price', 'category_id', 'created_at']);
}
```

### afterSearch(array $data)

Called after the search results have been fetched. Use this for logging or caching.

```php
protected function afterSearch(array $data): void
{
    // Log search analytics
    SearchLog::create([
        'user_id' => auth()->id(),
        'filters' => request()->get('filter', []),
        'sort' => request()->input('sort'),
        'results_count' => count($data),
    ]);

    // Cache popular searches
    if (empty(request()->get('filter'))) {
        Cache::put('products:latest', $data, now()->addMinutes(5));
    }
}
```

## Configuration

In `config/devToolbelt/fast-crud.php`:

```php
'search' => [
    'method' => 'toArray',  // Serialization method
    'per_page' => 40,       // Default items per page
],
```

## Request Examples

```bash
# Basic search with pagination
curl "http://api.example.com/products?perPage=20"

# Filtered and sorted
curl "http://api.example.com/products?filter[category_id][eq]=5&filter[price][lte]=100&sort=-created_at"

# Complex filtering
curl "http://api.example.com/products?filter[name][like]=phone&filter[status][in]=active,featured&filter[price][btw]=100,1000&sort=price&perPage=50"
```

## Response Example

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "name": "Product A",
            "price": 99.99,
            "category": {
                "id": 5,
                "name": "Electronics"
            }
        },
        {
            "id": 2,
            "name": "Product B",
            "price": 149.99,
            "category": {
                "id": 5,
                "name": "Electronics"
            }
        }
    ],
    "meta": {
        "pagination": {
            "current": 1,
            "perPage": 20,
            "pagesCount": 8,
            "count": 150
        }
    }
}
```

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ProductController extends CrudController
{
    protected function modelClassName(): string
    {
        return Product::query()->class;
    }

    protected function modifySearchQuery(Builder $query): void
    {
        // Eager load for performance
        $query->with([
            'category:id,name,slug',
            'brand:id,name',
            'images' => fn($q) => $q->limit(1),
        ]);

        // Only active products
        $query->where('is_active', true)
              ->whereNull('deleted_at');

        // Scope to user's store (multi-tenant)
        if (auth()->check() && !auth()->user()->isAdmin()) {
            $query->where('store_id', auth()->user()->store_id);
        }

        // Select only needed columns
        $query->select([
            'id', 'name', 'slug', 'price', 'sale_price',
            'category_id', 'brand_id', 'stock', 'created_at'
        ]);
    }

    protected function afterSearch(array $data): void
    {
        // Track search for analytics
        if (request()->has('filter')) {
            activity()
                ->withProperties([
                    'filters' => request()->get('filter'),
                    'results' => count($data),
                ])
                ->log('product_search');
        }
    }
}
```

## Advanced Filtering Tips

### Filtering by Relationship

```php
// To filter by relationship, add a custom scope or modify the query
protected function modifySearchQuery(Builder $query): void
{
    // Filter by category slug from request
    if ($categorySlug = request()->input('category')) {
        $query->whereHas('category', function ($q) use ($categorySlug) {
            $q->where('slug', $categorySlug);
        });
    }
}
```

### Date Range Filtering

```bash
# Use btw operator for date ranges
GET /products?filter[created_at][btw]=2024-01-01,2024-01-31
```

### Combining with Full-Text Search

```php
protected function modifySearchQuery(Builder $query): void
{
    if ($search = request()->input('q')) {
        $query->whereFullText(['name', 'description'], $search);
    }
}
```

## Performance Tips

1. **Always eager load relationships** in `modifySearchQuery`
2. **Select only needed columns** to reduce memory usage
3. **Add database indexes** on frequently filtered columns
4. **Use `perPage` wisely** - smaller pages load faster
5. **Cache popular searches** in `afterSearch`

## Related

- [API Query String Guide](search-query-string-guide.md) - Complete reference with SQL equivalents and best practices
- [Options Action](options.md) - Get dropdown values
- [Export CSV Action](export-csv.md) - Export search results
