<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class MariaDbEnvironment
{
    /** @return array<string, string> */
    public function getVariables(): array
    {
        // Dedicated disposable database only; never accepts a working database URL.
        return [
            'APP_ENV' => 'testing', 'APP_LOCALE' => 'ru', 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '33071',
            'DB_DATABASE' => 'homeassist_couples_testing', 'DB_USERNAME' => 'homeassist_test',
            'DB_PASSWORD' => 'test_only_password', 'DB_URL' => '', 'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'single',
            'BCRYPT_ROUNDS' => '4', 'REALTIME_ENABLED' => 'false', 'BROADCAST_CONNECTION' => 'null',
            'TELEGRAM_ENABLED' => 'false', 'TELEGRAM_BOT_TOKEN' => '',
            'TELEGRAM_BOT_USERNAME' => '', 'TELEGRAM_WEBHOOK_SECRET' => '', 'TELEGRAM_MODE' => 'webhook',
        ];
    }

    public function assertIsolated(Application $app): void
    {
        $config = $app->make('config');
        $variables = $this->getVariables();
        $isIsolated = $app->environment('testing') && ! $app->configurationIsCached()
            && $config->get('database.default') === 'mysql'
            && empty($config->get('database.connections.mysql.url'))
            && $config->get('database.connections.mysql.host') === $variables['DB_HOST']
            && (string) $config->get('database.connections.mysql.port') === $variables['DB_PORT']
            && $config->get('database.connections.mysql.database') === $variables['DB_DATABASE']
            && $config->get('database.connections.mysql.username') === $variables['DB_USERNAME'];
        if (! $isIsolated) {
            throw new RuntimeException('MariaDB tests require the dedicated disposable database on 127.0.0.1:33071.');
        }
        $config->set('database.connections', ['mysql' => $config->get('database.connections.mysql')]);
    }
}
