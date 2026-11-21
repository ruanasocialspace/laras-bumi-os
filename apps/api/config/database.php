<?php

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laras'),
            'username' => env('DB_USERNAME', 'laras'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8',
            'timezone' => 'UTC',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],
    ],
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => true],
    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => ['prefix' => env('REDIS_PREFIX', 'laras:')],
        'default' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => '0',
        ],
        'cache' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => '1',
        ],
        'queue' => [
            'host' => env('REDIS_QUEUE_HOST', '127.0.0.1'),
            'password' => env('REDIS_QUEUE_PASSWORD'),
            'port' => env('REDIS_QUEUE_PORT', '6379'),
            'database' => '0',
        ],
    ],
];
