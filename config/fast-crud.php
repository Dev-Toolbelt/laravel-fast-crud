<?php

declare(strict_types=1);

use DevToolbelt\Enums\Http\HttpStatusCode;

return [
    /*
    |--------------------------------------------------------------------------
    | Global Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings applied to all actions. Can be overridden per action.
    | - find_field: Database column used to find records (read, update, delete)
    | - find_field_is_uuid: Validate identifier as UUID before querying
    | - validation.http_status: HTTP status code for validation errors
    |
    */

    'global' => [
        'find_field' => 'id',
        'find_field_is_uuid' => false,
        'validation' => [
            'http_status' => HttpStatusCode::BAD_REQUEST->value,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Configuration
    |--------------------------------------------------------------------------
    |
    | Configure each CRUD action. Action-specific settings override global.
    | - method: Model serialization method ('toArray', 'toSoftArray', or custom)
    | - find_field: Database column used to find records (overrides global)
    | - find_field_is_uuid: Validate identifier as UUID (overrides global)
    | - per_page: Default pagination size (for search)
    | - http_status: HTTP status code for successful responses
    |
    */

    'create' => [
        'method' => 'toArray',
        'http_status' => HttpStatusCode::CREATED->value,
    ],

    'read' => [
        'method' => 'toArray',
        'http_status' => HttpStatusCode::OK->value,
    ],

    'update' => [
        'method' => 'toArray',
        'http_status' => HttpStatusCode::OK->value,
    ],

    'delete' => [
        'http_status' => HttpStatusCode::OK->value,
    ],

    'soft_delete' => [
        'deleted_at_field' => 'deleted_at',
        'deleted_by_field' => 'deleted_by',
        'http_status' => HttpStatusCode::OK->value,
    ],

    'restore' => [
        'method' => 'toArray',
        'http_status' => HttpStatusCode::OK->value,
    ],

    'search' => [
        'method' => 'toArray',
        'per_page' => 40,
        'http_status' => HttpStatusCode::OK->value,
    ],

    'options' => [
        'default_value' => 'id',
        'http_status' => HttpStatusCode::OK->value,
    ],

    'export_csv' => [
        'method' => 'toArray',
        'http_status' => HttpStatusCode::OK->value,
    ],
];
