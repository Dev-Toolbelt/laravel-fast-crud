# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel package for rapid CRUD API scaffolding. Reduces boilerplate when building RESTful APIs.

- **Package:** `dev-toolbelt/laravel-fast-crud`
- **Namespace:** `DevToolbelt\LaravelFastCrud`
- **PHP:** `^8.1` | **Laravel:** `^11.0`

## Commands

```bash
composer test              # Run PHPUnit tests
composer test:coverage     # Generate HTML coverage report (tests/coverage)
composer phpcs             # Run PHP CodeSniffer (PSR-12)
composer phpcs:fix         # Auto-fix code style issues
composer phpstan           # Run static analysis (level 6)
```

## Architecture

### Core Components

**CrudController** (`src/CrudController.php`) - Abstract base controller composing all CRUD traits. Subclasses implement `modelClassName(): string` to specify the Eloquent model.

**Router** (`src/Router.php`) - Static helper `Router::crud($uri, $controller, $module)` auto-generates RESTful routes with permission middleware.

### Actions (src/Actions/)

Traits implementing CRUD operations:
- **Create** - POST create, calls `beforeFill()` → `fill()` → `beforeSave()` → `save()` → `afterSave()`
- **Read** - GET by `external_id` (UUID), hook: `modifyReadQuery()`
- **Update** - PUT/PATCH/POST update by `external_id`
- **Delete** - DELETE by `external_id`, hook: `modifyDeleteQuery()`
- **Search** - GET list with filtering/sorting/pagination, hook: `modifySearchQuery()`
- **Options** - GET `/options` for select dropdowns, hook: `modifyOptionsQuery()`
- **ExportCsv** - GET `/export-csv`, hook: `modifyExportCsvQuery()`

### Query Traits (src/Traits/)

- **Searchable** - Filter with operators (`eq`, `neq`, `in`, `like`, `btw`, `gt`, `lt`, etc.) via `?filter[field][operator]=value`
- **Sortable** - Sort via `?sort=field,-desc_field`
- **Pageable** - Pagination via `?perPage=N` or `?skipPagination=true`
- **Limitable** - Query limit

### SearchOperator Enum (src/Enum/SearchOperator.php)

Defines operators: `eq`, `neq`, `in`, `nin`, `like`, `lt`, `gt`, `lte`, `gte`, `gtn`, `ltn`, `btw`, `json`, `nn`

### Configuration (config/fast-crud.php)

Global settings apply to all actions, but can be overridden per action:

```php
'global' => [
    'find_field' => 'id',
    'find_field_is_uuid' => false,
],
'create' => ['method' => 'toArray'],
'read' => ['method' => 'toArray'],
'update' => ['method' => 'toArray'],
'delete' => [],
'search' => ['method' => 'toArray', 'per_page' => 40],
'export_csv' => ['method' => 'toArray'],
```

- `find_field`: Database column for finding records (global or per action)
- `find_field_is_uuid`: Validate identifier as UUID before querying (global or per action)
- `method`: Model serialization method (`toArray`, `toSoftArray`, or custom)
- `per_page`: Default pagination size (search only)

Action-specific settings override global. Publish with:
```bash
php artisan vendor:publish --tag=fast-crud-config
```

## Key Patterns

1. **Configurable Find Field** - API uses `id` by default, configurable via `find_field` option
2. **Hook Methods** - Override `beforeFill()`, `beforeSave()`, `afterSave()`, `modifyXxxQuery()` for customization
3. **Trait Composition** - Actions are traits; include only what you need
4. **JSend Responses** - Uses `AnswerTrait` from `dev-toolbelt/jsend-payload` for response formatting
5. **Auto-Discovery** - ServiceProvider registered automatically via Laravel package discovery

## Model Requirements

Models must have:
- A unique identifier field (configured via `find_field`, default: `id`)
- Standard Eloquent `fillable` property
- `toArray()` method (or custom method configured via `method` option)

## Code Standards

- PSR-12 (phpcs.xml)
- PHPStan level 6
- Line length: 120 soft, 140 hard
- All files use `declare(strict_types=1)`
