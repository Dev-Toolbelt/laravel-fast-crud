# Options Action

The Options action handles GET requests to retrieve label-value pairs suitable for populating select dropdowns, autocomplete fields, and other UI components.

## Route

```
GET /{resource}/options
```

## Basic Usage

The action returns a simple array of objects with `label` and `value` properties:

```json
[
    {"label": "Electronics", "value": "uuid-1"},
    {"label": "Clothing", "value": "uuid-2"},
    {"label": "Home & Garden", "value": "uuid-3"}
]
```

## Query Parameters

| Parameter | Required | Default | Description |
|-----------|----------|---------|-------------|
| `label` | Yes | - | Column to use as display label |
| `value` | No | `external_id` | Column to use as value |

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
# Basic - using name as label, external_id as value (default)
GET /categories/options?label=name

# Custom value field
GET /categories/options?label=name&value=id

# Using slug as value
GET /categories/options?label=name&value=slug
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

Error - Missing Label (400):

```json
{
    "status": "fail",
    "data": {
        "message": "The label field is required"
    }
}
```

Error - Column Not Found (400):

```json
{
    "status": "fail",
    "data": {
        "message": "Column not found: label"
    }
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

## Frontend Usage Examples

### Vue.js

```vue
<template>
  <select v-model="selectedCategory">
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
    const response = await fetch('/api/categories/options?label=name&value=id')
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
    fetch('/api/categories/options?label=name&value=id')
      .then(res => res.json())
      .then(result => setOptions(result.data));
  }, []);

  return (
    <select value={value} onChange={e => onChange(e.target.value)}>
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

## Hierarchical Options

For parent-child relationships, use query parameters:

```bash
# Get root categories
GET /categories/options?label=name&value=id

# Get subcategories of parent
GET /categories/options?label=name&value=id&parent_id=5
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
