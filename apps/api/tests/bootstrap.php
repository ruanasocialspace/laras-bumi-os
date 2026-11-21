<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

// PHPUnit forces process and ENV values. Laravel may read inherited SERVER values first.
foreach ([
    'APP_ENV',
    'APP_DEBUG',
    'DB_CONNECTION',
    'CACHE_STORE',
    'SESSION_DRIVER',
    'SESSION_COOKIE',
    'SESSION_SECURE_COOKIE',
    'QUEUE_CONNECTION',
    'SANCTUM_STATEFUL_DOMAINS',
    'BCRYPT_ROUNDS',
] as $name) {
    $_SERVER[$name] = $_ENV[$name];
}
