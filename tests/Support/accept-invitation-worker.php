<?php

declare(strict_types=1);

use App\Actions\Couples\AcceptInvitation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\Support\MariaDbEnvironment;

require sprintf('%s/vendor/autoload.php', dirname(__DIR__, 2));
$app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2));
$app->make(Kernel::class)->bootstrap();
(new MariaDbEnvironment)->assertIsolated($app);
$actorId = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT);
$token = $argv[2] ?? '';
$barrier = $argv[3] ?? '';
if (! is_int($actorId) || $token === '' || $barrier === '') {
    throw new RuntimeException('Invalid concurrency worker arguments.');
}
$actor = User::query()->findOrFail($actorId);
fwrite(STDOUT, "ready\n");
$deadline = microtime(true) + 10; // Bound the child wait if the parent fails.
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrency barrier timed out.');
    }
    usleep(10000); // Check the barrier every 10ms without a busy loop.
}
try {
    $app->make(AcceptInvitation::class)->execute($actor, $token);
    fwrite(STDOUT, "accepted\n");
} catch (ValidationException) {
    fwrite(STDOUT, "rejected\n");
}
