<?php

declare(strict_types=1);

use App\Actions\Admin\AdjustBalance;
use App\Actions\Admin\UpdateAppearance;
use App\Data\Admin\AppearanceChangeData;
use App\Data\Admin\BalanceAdjustmentData;
use App\Models\User;
use App\Services\Admin\PanelAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Validation\ValidationException;
use Tests\Support\MariaDbEnvironment;

require sprintf('%s/vendor/autoload.php', dirname(__DIR__, 2));
$app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2));
$app->make(Kernel::class)->bootstrap();
(new MariaDbEnvironment)->assertIsolated($app);
$actor = User::query()->findOrFail($argv[1] ?? '');
$target = User::query()->findOrFail($argv[2] ?? '');
$operation = $argv[3] ?? '';
$key = $argv[4] ?? '';
$amount = filter_var($argv[5] ?? '', FILTER_VALIDATE_INT);
$barrier = $argv[6] ?? '';
if (! is_int($amount) || $key === '' || $barrier === '') {
    throw new RuntimeException('Invalid admin concurrency arguments.');
}
$hash = 'test-only-panel-fingerprint';
config(['admin_panel.login' => $actor->login, 'admin_panel.password_hash' => $hash]);
$session = new Store('test-admin-panel', new ArraySessionHandler(30));
$session->start();
$session->put('admin_panel', ['owner' => $actor->id, 'until' => now()->getTimestamp() + PanelAccess::SESSION_SECONDS, 'fingerprint' => hash('sha256', $hash)]);
$request = Request::create('/admin-panel/appearance', 'PUT');
$request->setLaravelSession($session);
$app->instance('request', $request);
$request->setUserResolver(fn (): User => $actor);
fwrite(STDOUT, "ready\n");
$deadline = microtime(true) + 10; // Bound the wait if the parent exits.
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Admin concurrency barrier timed out.');
    }
    usleep(10000); // Poll the barrier every 10ms without a busy loop.
}
try {
    match ($operation) {
        'balance' => $app->make(AdjustBalance::class)->execute($actor, $target, new BalanceAdjustmentData($amount, $key, 'Concurrent test adjustment'), $request),
        'appearance' => $app->make(UpdateAppearance::class)->execute($actor, new AppearanceChangeData(section: 'icons', revision: $amount, icon: $key, kind: 'glyph', glyph: '♥'), $request),
        default => throw new RuntimeException('Unsupported admin concurrency operation.'),
    };
    fwrite(STDOUT, "accepted\n");
} catch (ValidationException) {
    fwrite(STDOUT, "rejected\n");
}
