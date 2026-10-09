<?php

declare(strict_types=1);

use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Tests\Support\MariaDbEnvironment;
use Tests\Support\TestEnvironment;

it('rejects persistent storage', function (): void {
    config(['database.connections.sqlite.database' => 'working.sqlite']);
    expect(fn (): null => (new TestEnvironment)->assertIsolated($this->app))->toThrow(RuntimeException::class);
});

it('rejects a non-testing application', function (): void {
    $this->app->instance('env', 'production');
    expect(fn (): null => (new TestEnvironment)->assertIsolated($this->app))->toThrow(RuntimeException::class);
});

it('rejects database URLs that override memory storage', function (): void {
    config(['database.connections.sqlite.url' => 'sqlite:///working.sqlite']);
    expect(fn (): null => (new TestEnvironment)->assertIsolated($this->app))->toThrow(RuntimeException::class);
});

it('prevents unexpected external HTTP calls', function (): void {
    expect(fn (): Response => Http::get('https://example.com'))->toThrow(StrayRequestException::class);
});

it('removes connections to persistent databases from the test application', function (): void {
    expect(array_keys(config('database.connections')))->toBe(['sqlite']);
});

it('refuses MariaDB migrations unless the dedicated test connection matches exactly', function (string $key, string $value): void {
    config([
        'database.default' => 'mysql',
        'database.connections.mysql' => [
            'url' => '', 'host' => '127.0.0.1', 'port' => '33071',
            'database' => 'homeassist_couples_testing', 'username' => 'homeassist_test',
        ],
    ]);
    config([$key => $value]);
    expect(fn (): null => (new MariaDbEnvironment)->assertIsolated($this->app))->toThrow(RuntimeException::class);
})->with([
    ['database.connections.mysql.database', 'working'],
    ['database.connections.mysql.port', '33070'],
    ['database.connections.mysql.host', 'mariadb'],
    ['database.connections.mysql.username', 'sail'],
    ['database.connections.mysql.url', 'mysql://working'],
]);
