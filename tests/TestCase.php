<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        // Refuse to run database tests against any on-disk or live database.
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            if ($app['config']->get('database.default') !== 'sqlite'
                || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
                throw new \LogicException('Tests require SQLite :memory:.');
            }
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }
}
