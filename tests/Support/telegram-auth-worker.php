<?php

declare(strict_types=1);

use App\Actions\Auth\Telegram\FinishTelegramLogin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\Support\MariaDbEnvironment;

require sprintf('%s/vendor/autoload.php', dirname(__DIR__, 2));
$app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2));
$app->make(Kernel::class)->bootstrap();
(new MariaDbEnvironment)->assertIsolated($app);
$attemptId = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT);
$browserToken = $argv[2] ?? '';
$barrier = $argv[3] ?? '';
if (! is_int($attemptId) || $browserToken === '' || $barrier === '') {
    throw new RuntimeException('Invalid auth worker arguments.');
}
fwrite(STDOUT, "ready\n");
$deadline = microtime(true) + 10; // Bound the test barrier wait.
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Auth concurrency barrier timed out.');
    }
    usleep(10000); // Match the common race test protocol.
}
try {
    $app->make(FinishTelegramLogin::class)->execute($attemptId, $browserToken, null);
    fwrite(STDOUT, "accepted\n");
} catch (ValidationException) {
    fwrite(STDOUT, "rejected\n");
}
