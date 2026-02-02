<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Model Serialization Methods
    |--------------------------------------------------------------------------
    |
    | Define the default model method to call for serializing responses.
    | Each action can have its own serialization method configured.
    | Common options: 'toArray', 'toSoftArray', or any custom method.
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

    'search' => [
        'method' => 'toArray',
    ],

    'export_csv' => [
        'method' => 'toArray',
    ],
];
