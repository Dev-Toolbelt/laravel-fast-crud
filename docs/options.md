# Options Action

The Options action handles GET requests to retrieve label-value pairs specifically designed for populating HTML `<select>` elements, autocomplete fields, comboboxes, and similar UI components that require a list of choices.

This endpoint is optimized for frontend select components, returning a simple and consistent format that can be directly consumed by popular frontend frameworks like Vue.js, React, Angular, or vanilla JavaScript.

## Route

```
GET /{resource}/options
```

## Basic Usage

The action returns a simple array of objects with `label` and `value` properties, ready to be used in HTML `<select>` elements:

```json
[
    {"label": "Electronics", "value": 1},
    {"label": "Clothing", "value": 2},
    {"label": "Home & Garden", "value": 3}
]
```

Each object represents an `<option>` element where:
- `label` = the text displayed to the user
- `value` = the value submitted when the option is selected

## Query Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `label` | Yes | - | Column to use as display label (text shown to user) |
| `value` | No | `id` (configurable) | Column to use as the option value (submitted when selected) |

## Configuration

Configure the default value column in `config/fast-crud.php`:

```php
use DevToolbelt\Enums\Http\HttpStatusCode;

'options' => [
    'default_value' => 'id', // Default column for option values
    'http_status' => HttpStatusCode::OK->value, // 200
],
```

This allows you to set a project-wide default for the `value` parameter. For example, if your application uses UUIDs:

```php
'options' => [
    'default_value' => 'uuid',
],
```

### http_status

The HTTP status code returned on successful options retrieval. Default: `200 OK`.

```php
use DevToolbelt\Enums\Http\HttpStatusCode;

// Return 202 Accepted instead of 200 OK
'options' => [
    'http_status' => HttpStatusCode::ACCEPTED->value,
],
```

## Lifecycle

```
Request → Validate Parameters → Validate Columns → modifyOptionsQuery() → Fetch → Format → afterOptions() → Response
```

## Hooks

### modifyOptionsQuery(Builder $query)

Modify the query before fetching options. Use this for filtering and ordering.

```php
protected function modifyOptionsQuery(Builder $query): void
{
    // Only active items
    $query->where('is_active', true);

    // Exclude soft deleted
    $query->whereNull('deleted_at');

    // Scope to current user's store
    $query->where('store_id', auth()->user()->store_id);

    // Custom ordering
    $query->orderBy('sort_order');
}
```

### afterOptions(array $rows)

Called after options have been fetched and formatted. Use this for caching or transformation.

```php
protected function afterOptions(array $rows): void
{
    // Cache the results
    $cacheKey = "options:{$this->modelClassName()}:" . md5(json_encode(request()->all()));
    Cache::put($cacheKey, $rows, now()->addMinutes(30));

    // Log for analytics
    activity()
        ->withProperties(['count' => count($rows)])
        ->log('options_fetched');
}
```

## Request Examples

```bash
# Basic - using name as label, id as value (default)
GET /categories/options?label=name

# Using UUID as value
GET /categories/options?label=name&value=uuid

# Using slug as value (useful for URL-friendly selects)
GET /categories/options?label=name&value=slug

# Using external_id as value
GET /categories/options?label=name&value=external_id
```

## Response Examples

Success (200 OK):

```json
{
    "status": "success",
    "data": [
        {"label": "Electronics", "value": 1},
        {"label": "Clothing", "value": 2},
        {"label": "Home & Garden", "value": 3}
    ]
}
```

Error - Missing Label (uses `global.validation.http_status`, default: 400):

```json
{
    "status": "fail",
    "data": [
        {
            "field": "label",
            "error": "required",
            "message": "The label field is required"
        }
    ],
    "meta": []
}
```

Error - Column Not Found (uses `global.validation.http_status`, default: 400):

```json
{
    "status": "fail",
    "data": [
        {
            "field": "label",
            "error": "columnNotFound",
            "message": "Column not found: label"
        }
    ],
    "meta": []
}
```

## Complete Example

```php
<?php

namespace App\Http\Controllers;

use App\Models\Category;
use DevToolbelt\LaravelFastCrud\CrudController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class CategoryController extends CrudController
{
    protected function modelClassName(): string
    {
        return Category::class;
    }

    protected function modifyOptionsQuery(Builder $query): void
    {
        // Only show active categories
        $query->where('is_active', true)
              ->whereNull('deleted_at');

        // Order by name for better UX
        $query->orderBy('name', 'asc');

        // Optionally filter by parent for hierarchical selects
        if ($parentId = request()->input('parent_id')) {
            $query->where('parent_id', $parentId);
        }
    }

    protected function afterOptions(array $rows): void
    {
        // Cache frequently accessed options
        $cacheKey = 'category_options:' . (request()->input('parent_id') ?? 'root');
        Cache::tags(['categories', 'options'])->put($cacheKey, $rows, now()->addHours(1));
    }
}
```

## HTML Select Usage

The response format is designed to directly populate HTML `<select>` elements:

```html
<select name="category_id" id="category">
    <option value="">Select a category</option>
    <!-- Options populated from API response -->
    <option value="1">Electronics</option>
    <option value="2">Clothing</option>
    <option value="3">Home & Garden</option>
</select>
```

The `value` property becomes the `value` attribute of `<option>`, and `label` becomes the displayed text.

## Frontend Usage Examples

### Vue.js

```vue
<template>
  <select v-model="selectedCategory" name="category_id">
    <option value="">Select a category</option>
    <option v-for="option in categories" :key="option.value" :value="option.value">
      {{ option.label }}
    </option>
  </select>
</template>

<script>
export default {
  data() {
    return {
      selectedCategory: '',
      categories: []
    }
  },
  async mounted() {
    // Uses 'id' as default value column (configurable in config/fast-crud.php)
    const response = await fetch('/api/categories/options?label=name')
    const result = await response.json()
    this.categories = result.data
  }
}
</script>
```

### React

```jsx
import { useState, useEffect } from 'react';

function CategorySelect({ value, onChange }) {
  const [options, setOptions] = useState([]);

  useEffect(() => {
    // Uses 'id' as default value column (configurable in config/fast-crud.php)
    fetch('/api/categories/options?label=name')
      .then(res => res.json())
      .then(result => setOptions(result.data));
  }, []);

  return (
    <select name="category_id" value={value} onChange={e => onChange(e.target.value)}>
      <option value="">Select a category</option>
      {options.map(option => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
}
```

## Hierarchical Options (Dependent Selects)

For parent-child relationships (e.g., Country > State > City), use query parameters to filter options:

```bash
# Get root categories (for first select)
GET /categories/options?label=name

# Get subcategories of parent (for dependent select)
GET /categories/options?label=name&parent_id=5
```

Handle in controller:

```php
protected function modifyOptionsQuery(Builder $query): void
{
    if (request()->has('parent_id')) {
        $query->where('parent_id', request()->input('parent_id'));
    } else {
        $query->whereNull('parent_id'); // Root items only
    }
}
```

## With Grouping

For grouped selects (optgroup), create a custom endpoint:

```php
public function groupedOptions(): JsonResponse
{
    $categories = Category::with('children')
        ->whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('name')
        ->get()
        ->map(fn($cat) => [
            'label' => $cat->name,
            'options' => $cat->children->map(fn($child) => [
                'label' => $child->name,
                'value' => $child->id,
            ]),
        ]);

    return $this->answerSuccess($categories->toArray());
}
```

## Performance Optimization

For large datasets, consider:

1. **Caching** in `afterOptions`
2. **Limiting results** in `modifyOptionsQuery`
3. **Selecting only needed columns** (already done by the action)

```php
protected function modifyOptionsQuery(Builder $query): void
{
    // Limit to prevent huge payloads
    $query->limit(100);

    // Or paginate for autocomplete
    if ($search = request()->input('q')) {
        $query->where('name', 'like', "%{$search}%")
              ->limit(20);
    }
}
```

## Related

- [Search Action](search.md) - Full record listing with filters
