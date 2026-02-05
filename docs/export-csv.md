# Export CSV Action

The Export CSV action handles GET requests to export filtered and sorted records to a downloadable CSV file.

## Route

```
GET /{resource}/export-csv
```

## Basic Usage

The action supports the same filter and sort parameters as the [Search Action](search.md), allowing users to export exactly what they see.

## Configuration Properties

Configure the export in your controller:

```php
class ProductController extends CrudController
{
    /**
     * The filename for the exported CSV.
     * Will be prefixed with timestamp: 2024-01-15_10-30-00_products.csv
     */
    protected string $csvFileName = 'products.csv';

    /**
     * Column mapping: model attribute => CSV header
     * Supports dot notation for relationships.
     */
    protected array $csvColumns = [
        'id' => 'ID',
        'name' => 'Product Name',
        'category.name' => 'Category',
        'price' => 'Price',
        'stock' => 'Stock',
        'created_at' => 'Created Date',
    ];
}
```

## Column Mapping

### Associative Array (Recommended)

Map model attributes/paths to custom headers:

```php
protected array $csvColumns = [
    'name' => 'Product Name',
    'sku' => 'SKU',
    'category.name' => 'Category',
    'brand.name' => 'Brand',
    'price' => 'Unit Price',
    'stock' => 'Quantity in Stock',
    'status' => 'Status',
    'created_at' => 'Created At',
];
```

### Indexed Array

Use attribute names as headers:

```php
protected array $csvColumns = [
    'name',
    'sku',
    'price',
    'stock',
];
```

### Dot Notation for Relationships

Access nested relationship data:

```php
protected array $csvColumns = [
    'name' => 'Product',
    'category.name' => 'Category',           // belongsTo
    'brand.country.name' => 'Brand Country', // Nested relationship
    'supplier.contact.email' => 'Supplier Email',
];
```

## Lifecycle

```
Request → modifyExportCsvQuery() → Apply Filters → Apply Sorting → Fetch All → Stream CSV → Response
```

## Hooks

### modifyExportCsvQuery(Builder $query)

Modify the query before filters are applied. Essential for eager loading relationships used in `$csvColumns`.

```php
protected function modifyExportCsvQuery(Builder $query): void
{
    // IMPORTANT: Eager load all relationships used in csvColumns
    $query->with([
        'category:id,name',
        'brand:id,name',
        'supplier:id,name,email',
    ]);

    // Add base conditions
    $query->where('is_active', true)
          ->whereNull('deleted_at');

    // Scope to user's data
    $query->where('store_id', auth()->user()->store_id);
}
```

## Request Examples

```bash
# Export all products
GET /products/export-csv

# Export filtered products
GET /products/export-csv?filter[category_id][eq]=5

# Export with sorting
GET /products/export-csv?filter[status][eq]=active&sort=-created_at

# Complex export
GET /products/export-csv?filter[price][btw]=100,500&filter[status][in]=active,featured&sort=name
```

## Configuration

In `config/devToolbelt/fast-crud.php`:

```php
use DevToolbelt\Enums\Http\HttpStatusCode;

'export_csv' => [
    'method' => 'toArray',  // Serialization method
    'http_status' => HttpStatusCode::OK->value, // 200
],
```

### http_status

The HTTP status code returned on successful export. Default: `200 OK`.

```php
use DevToolbelt\Enums\Http\HttpStatusCode;

// Return 202 Accepted instead of 200 OK
'export_csv' => [
    'http_status' => HttpStatusCode::ACCEPTED->value,
],
```

## Response

The response is a streamed CSV file download with optimized headers for file streaming:

```
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="2024-01-15_10-30-00_products.csv"
Content-Length: 12345
Content-Transfer-Encoding: binary
Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate
Pragma: no-cache
Expires: 0
Last-Modified: Thu, 15 Jan 2024 10:30:00 GMT
X-Content-Type-Options: nosniff
Accept-Ranges: none
```

These headers ensure:
- **Content-Length**: Allows download progress indicators
- **Cache-Control/Pragma/Expires**: Prevents caching of the CSV file
- **X-Content-Type-Options**: Prevents MIME type sniffing for security
- **Accept-Ranges**: Indicates that range requests are not supported

## CSV Output Example

```csv
ID,Product Name,Category,Price,Stock,Status
1,iPhone 15 Pro,Electronics,999.99,50,active
2,Samsung Galaxy S24,Electronics,899.99,75,active
3,Nike Air Max,Footwear,149.99,200,active
```

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;

class ProductController extends CrudController
{
    protected string $csvFileName = 'products.csv';

    protected array $csvColumns = [
        'id' => 'ID',
        'sku' => 'SKU',
        'name' => 'Product Name',
        'category.name' => 'Category',
        'brand.name' => 'Brand',
        'price' => 'Price ($)',
        'sale_price' => 'Sale Price ($)',
        'stock' => 'Stock',
        'status' => 'Status',
        'created_at' => 'Created',
        'updated_at' => 'Last Updated',
    ];

    protected function modelClassName(): string
    {
        return Product::query()->class;
    }

    protected function modifyExportCsvQuery(Builder $query): void
    {
        // Eager load relationships for csvColumns
        $query->with([
            'category:id,name',
            'brand:id,name',
        ]);

        // Only export active, non-deleted products
        $query->where('is_active', true)
              ->whereNull('deleted_at');

        // Scope to user's store
        $query->where('store_id', auth()->user()->store_id);

        // Order for consistent exports
        $query->orderBy('category_id')
              ->orderBy('name');
    }
}
```

## Handling Enums

The action automatically handles `BackedEnum` values:

```php
// In your model
enum ProductStatus: string
{
    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';
}

// Will export as: active, draft, archived
```

## Handling Large Exports

The action uses streaming for memory efficiency, but for very large datasets:

### 1. Add Query Limits

```php
protected function modifyExportCsvQuery(Builder $query): void
{
    // Limit export size
    $query->limit(10000);
}
```

### 2. Use Queue for Background Export

Create a custom export job:

```php
public function exportLargeCsv(Request $request): JsonResponse
{
    ExportProductsCsvJob::dispatch(
        auth()->user(),
        $request->get('filter', []),
        $request->input('sort', '')
    );

    return $this->answerSuccess([
        'message' => 'Export started. You will receive an email when ready.'
    ]);
}
```

## Customizing the Filename

Override based on filters:

```php
protected function getExportFileName(): string
{
    $base = 'products';

    if ($category = request()->input('filter.category_id.eq')) {
        $categoryName = Category::find($category)?->slug ?? $category;
        $base = "products-{$categoryName}";
    }

    return "{$base}.csv";
}
```

Then use in the trait or override `exportCsv` method.

## Special Characters Handling

The action properly escapes:
- Commas within values
- Newlines within values
- Double quotes (escaped as `""`)

This follows RFC 4180 CSV specification.

## Frontend Integration

### Download Button

```html
<a href="/api/products/export-csv?filter[status][eq]=active"
   class="btn btn-primary"
   download>
  Export Active Products
</a>
```

### With Current Filters (JavaScript)

```javascript
function exportCsv() {
  const params = new URLSearchParams(window.location.search);
  window.location.href = `/api/products/export-csv?${params.toString()}`;
}
```

### React Example

```jsx
function ExportButton({ filters, sort }) {
  const handleExport = () => {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([field, operators]) => {
      Object.entries(operators).forEach(([op, value]) => {
        params.append(`filter[${field}][${op}]`, value);
      });
    });

    if (sort) params.append('sort', sort);

    window.location.href = `/api/products/export-csv?${params.toString()}`;
  };

  return <button onClick={handleExport}>Export CSV</button>;
}
```

## Related

- [Search Action](search.md) - Same filtering/sorting capabilities
