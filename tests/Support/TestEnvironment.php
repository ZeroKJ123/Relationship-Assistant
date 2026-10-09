<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class TestEnvironment
{
    public function assertIsolated(Application $app): void
    {
        $config = $app->make('config');
        $isMemoryDatabase = $config->get('database.default') === 'sqlite'
            && $config->get('database.connections.sqlite.database') === ':memory:'
            && empty($config->get('database.connections.sqlite.url'));

        if (! $app->environment('testing') || ! $isMemoryDatabase || $app->configurationIsCached()) {
            $app->make('log')->error('test_environment.refused', ['reason' => 'non_isolated_configuration']);

            throw new RuntimeException('Tests require uncached testing configuration and SQLite :memory:.');
        }

        $app->make('log')->debug('test_environment.ready', ['driver' => 'sqlite', 'storage' => 'memory']);
    }
}
