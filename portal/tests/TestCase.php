<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') === 'mysql' && ! str_ends_with((string) $app['db']->connection()->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('MySQL tests require a disposable database whose name ends in _testing. Never use the hosting database.');
        }

        return $app;
    }
}
