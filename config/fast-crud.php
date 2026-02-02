<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Global Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings applied to all actions. Can be overridden per action.
    | - find_field: Database column used to find records (read, update, delete)
    | - find_field_is_uuid: Validate identifier as UUID before querying
    |
    */

    'global' => [
        'find_field' => 'id',
        'find_field_is_uuid' => false,
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
    |
    */

    'create' => [
        'method' => 'toArray',
    ],

    'read' => [
        'method' => 'toArray',
    ],

    'update' => [
        'method' => 'toArray',
    ],

    'delete' => [
    ],

    'search' => [
        'method' => 'toArray',
        'per_page' => 40,
    ],

    'export_csv' => [
        'method' => 'toArray',
    ],
];
