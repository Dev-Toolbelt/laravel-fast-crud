# API Query String Guide

Complete guide for querying and filtering API endpoints in New Chronos API.

**Reference:** [Yii Framework REST Filtering](https://www.yiiframework.com/doc/guide/2.0/en/rest-filtering-collections#filtering-request)

## Table of Contents

1. [Overview](#overview)
2. [Filtering](#filtering)
3. [Operators Reference](#operators-reference)
4. [Sorting](#sorting)
5. [Pagination](#pagination)
6. [Practical Examples](#practical-examples)

---

## Overview

The API supports three main query parameters:

- **`filter`** - Filter records by specific criteria
- **`sort`** - Order results by one or more fields
- **`perPage`** - Limit results per page (pagination)

**Base URL Format:**
```
GET /api/endpoint?filter[field][operator]=value&sort=field&perPage=20
```

---

## Filtering

### Basic Filter (Exact Match)

Returns records where the field exactly matches the value.

**Syntax:** `filter[fieldName]=value`

**Example:**
```
GET /phone-types?filter[name]=Mobile

SQL: WHERE name = 'Mobile'
```

### LIKE Filter (Partial Match)

Returns records containing the value (case-insensitive partial match).

**Syntax:** `filter[fieldName][like]=value`

**Example:**
```
GET /phone-types?filter[name][like]=Mobile

SQL: WHERE name LIKE '%Mobile%'
```

### IN Filter (Multiple Values)

Returns records matching any of the comma-separated values.

**Syntax:** `filter[fieldName][in]=A,B,C`

**Example:**
```
GET /phone-types?filter[name][in]=Mobile,Home,Work

SQL: WHERE name IN ('Mobile', 'Home', 'Work')
```

### NOT IN Filter

Returns records NOT matching any of the values.

**Syntax:** `filter[fieldName][nin]=A,B,C`

**Example:**
```
GET /phone-types?filter[name][nin]=Fax,Pager

SQL: WHERE name NOT IN ('Fax', 'Pager')
```

### Comparison Filters

**Less Than (lt):**
```
GET /devices?filter[batteryLevel][lt]=20

SQL: WHERE batteryLevel < 20
```

**Less Than or Equal (lte):**
```
GET /devices?filter[batteryLevel][lte]=15

SQL: WHERE batteryLevel <= 15
```

**Greater Than (gt):**
```
GET /devices?filter[batteryLevel][gt]=80

SQL: WHERE batteryLevel > 80
```

**Greater Than or Equal (gte):**
```
GET /devices?filter[batteryLevel][gte]=85

SQL: WHERE batteryLevel >= 85
```

**Equal (eq):**
```
GET /users?filter[active][eq]=1

SQL: WHERE active = 1
```

**Not Equal (neq):**
```
GET /users?filter[active][neq]=0

SQL: WHERE active != 0
```

### BETWEEN Filter

Returns records within a range (inclusive).

**Syntax:** `filter[fieldName][btw]=start,end`

**Example:**
```
GET /events?filter[createdAt][btw]=2024-01-01,2024-12-31

SQL: WHERE createdAt BETWEEN '2024-01-01' AND '2024-12-31'
```

### JSON Filter

Filter by values within JSON columns.

**Syntax:** `filter[jsonField][json][subField]=value`

**Single Value:**
```
GET /devices?filter[metadata][json][status]=active

SQL: WHERE JSON_CONTAINS(metadata, '"active"', '$.status')
```

**Multiple Values (OR):**
```
GET /devices?filter[metadata][json][status]=active,pending

SQL: WHERE JSON_CONTAINS(metadata, '"active"', '$.status')
         OR JSON_CONTAINS(metadata, '"pending"', '$.status')
```

### Multiple Filters (AND)

Combine multiple filters - they are combined with AND logic.

**Example:**
```
GET /phone-types?filter[personType]=1&filter[name][like]=Mobile

SQL: WHERE personType = 1 AND name LIKE '%Mobile%'
```

---

## Operators Reference

Complete list of available operators:

| Operator | SQL Equivalent | Description |
|----------|---------------|-------------|
| `eq` | `=` | Equal to |
| `neq` | `!=` | Not equal to |
| `lt` | `<` | Less than |
| `lte` | `<=` | Less than or equal |
| `gt` | `>` | Greater than |
| `gte` | `>=` | Greater than or equal |
| `like` | `LIKE` | Pattern match (case-insensitive) |
| `in` | `IN` | Value in list |
| `nin` | `NOT IN` | Value not in list |
| `btw` | `BETWEEN` | Between two values (inclusive) |

**Usage Pattern:**
```
filter[fieldName][operator]=value
```

---

## Sorting

### Single Field Sort

**Ascending (default):**
```
GET /phone-types?sort=name

SQL: ORDER BY name ASC
```

**Descending (prefix with `-`):**
```
GET /phone-types?sort=-name

SQL: ORDER BY name DESC
```

### Multiple Fields Sort

Separate fields with commas. Use `-` prefix for descending order.

**Example:**
```
GET /events?sort=priority,-createdAt

SQL: ORDER BY priority ASC, createdAt DESC
```

**Common Patterns:**
- `sort=name` - Sort by name ascending
- `sort=-createdAt` - Sort by creation date descending (newest first)
- `sort=status,-updatedAt` - Sort by status ascending, then updated date descending

---

## Pagination

### Basic Pagination

**Syntax:** `perPage=N`

**Example:**
```
GET /phone-types?perPage=50

Returns 50 results per page
```

### Page Navigation

**Syntax:** `page=N&perPage=M`

**Example:**
```
GET /phone-types?page=2&perPage=20

Returns page 2 with 20 results per page (records 21-40)
```

### Skip Pagination

Return all results without pagination (use with caution on large datasets).

**Syntax:** `skipPagination=1`

**Example:**
```
GET /phone-types?skipPagination=1

Returns ALL records (no pagination)
```

### Pagination Response

The API returns pagination metadata in the response:

```json
{
  "status": "success",
  "data": [...],
  "meta": {
    "pagination": {
      "current": 1,
      "perPage": 20,
      "pagesCount": 5,
      "recordsCount": 100,
      "count": 20
    }
  }
}
```

**Fields:**
- `current` - Current page number
- `perPage` - Results per page
- `pagesCount` - Total number of pages
- `recordsCount` - Number of records in current page
- `count` - Total number of records

---

## Practical Examples

### Example 1: Search Users

Find active users created in 2024, sorted by name:

```
GET /users?filter[active]=1&filter[createdAt][btw]=2024-01-01,2024-12-31&sort=name&perPage=50
```

### Example 2: Search Devices

Find devices with battery below 20% or offline status:

```
GET /devices?filter[batteryLevel][lt]=20&perPage=100
GET /devices?filter[status]=offline&sort=-lastSeenAt
```

### Example 3: Complex Phone Types Query

Find phone types for persons with "Mobile" in the name:

```
GET /phone-types?filter[personType]=1&filter[name][like]=Mobile&sort=name
```

### Example 4: Date Range with Sorting

Events in January 2024, sorted by priority and date:

```
GET /events?filter[createdAt][btw]=2024-01-01,2024-01-31&sort=priority,-createdAt&perPage=25
```

### Example 5: Multiple Exact Values

Users from specific departments:

```
GET /users?filter[department][in]=IT,Engineering,Support&sort=name
```

### Example 6: Exclude Values

All phone types except specific ones:

```
GET /phone-types?filter[name][nin]=Fax,Pager,Landline
```

### Example 7: Options Endpoint

Get select options for a field:

```
GET /phone-types/options?label=name&filter[personType]=1

Returns: [
  {"label": "Mobile", "value": "uuid-1"},
  {"label": "Home", "value": "uuid-2"}
]
```

---

## Query String Best Practices

1. **URL Encode Values** - Always encode special characters:
   ```
   ✅ filter[name][like]=M%26M%20Phone
   ❌ filter[name][like]=M&M Phone
   ```

2. **Use Specific Filters** - More specific filters improve performance:
   ```
   ✅ filter[createdAt][btw]=2024-01-01,2024-01-31
   ❌ filter[createdAt][like]=2024-01
   ```

3. **Limit Results** - Always use pagination for large datasets:
   ```
   ✅ ?perPage=50
   ❌ ?skipPagination=1 (on large tables)
   ```

4. **Combine Filters Wisely** - Use AND logic with multiple filters:
   ```
   ✅ ?filter[active]=1&filter[role]=admin
   ```

5. **Sort by Indexed Fields** - For better performance:
   ```
   ✅ ?sort=id (indexed)
   ✅ ?sort=createdAt (indexed)
   ❌ ?sort=metadata (not indexed)
   ```
