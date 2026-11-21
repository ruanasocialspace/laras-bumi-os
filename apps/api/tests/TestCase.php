<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $testDatabase = $app['config']->get('laras.test_database');
        if (!$app->environment('testing') || !is_string($testDatabase) || !preg_match('/\A[a-z][a-z0-9_]*_test\z/', $testDatabase)) {
            throw new \LogicException('Tests require an isolated PostgreSQL database ending in _test.');
        }

        // RefreshDatabase may recreate tables. It must never inherit the app database.
        $app['config']->set('database.connections.pgsql.database', $testDatabase);

        return $app;
    }
}
