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

## Key Patterns

1. **External ID** - API uses `external_id` (UUID) field, not internal `id`
2. **Hook Methods** - Override `beforeFill()`, `beforeSave()`, `afterSave()`, `modifyXxxQuery()` for customization
3. **Trait Composition** - Actions are traits; include only what you need
4. **JSend Responses** - Uses `AnswerTrait` from `dev-toolbelt/jsend-payload` for response formatting

## Model Requirements

Models must have:
- `external_id` field (UUID)
- Standard Eloquent `fillable` property
- `toSoftArray()` or `toArray()` methods

## Code Standards

- PSR-12 (phpcs.xml)
- PHPStan level 6
- Line length: 120 soft, 140 hard
- All files use `declare(strict_types=1)`
