<?php

declare(strict_types=1);

use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Couples\Agreements\ProposeWeeklyLimit;
use App\Actions\Couples\Agreements\SubmitAnswer;
use App\Actions\Couples\EndCouple;
use App\Data\Couples\WeeklyLimitData;
use App\Models\AgreementProposal;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Tests\Support\MariaDbEnvironment;

require sprintf('%s/vendor/autoload.php', dirname(__DIR__, 2));
$app = require sprintf('%s/bootstrap/app.php', dirname(__DIR__, 2));
$app->make(Kernel::class)->bootstrap();
(new MariaDbEnvironment)->assertIsolated($app);
$actor = User::query()->findOrFail($argv[1] ?? '');
$couple = Couple::query()->findOrFail($argv[2] ?? '');
$operation = $argv[3] ?? '';
$value = filter_var($argv[4] ?? '', FILTER_VALIDATE_INT);
$limit = filter_var($argv[5] ?? '', FILTER_VALIDATE_INT);
$barrier = $argv[6] ?? '';
if (! is_int($value) || ! is_int($limit) || $barrier === '') {
    throw new RuntimeException('Invalid agreement worker arguments.');
}
fwrite(STDOUT, "ready\n");
$deadline = microtime(true) + 10; // Bound the wait if the parent exits.
while (! file_exists($barrier)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Agreement concurrency barrier timed out.');
    }
    usleep(10000); // Poll the barrier every 10ms without a busy loop.
}
try {
    match ($operation) {
        'accept' => $app->make(AcceptProposal::class)->execute($actor, $couple, AgreementProposal::query()->findOrFail($value)),
        'answer' => $app->make(SubmitAnswer::class)->execute($actor, $couple, new WeeklyLimitData($limit, $value)),
        'propose' => $app->make(ProposeWeeklyLimit::class)->execute($actor, $couple, new WeeklyLimitData($limit, $value)),
        'end' => $app->make(EndCouple::class)->execute($actor, $couple),
        default => throw new RuntimeException('Unknown concurrency operation.'),
    };
    fwrite(STDOUT, "accepted\n");
} catch (ValidationException|AuthorizationException) {
    fwrite(STDOUT, "rejected\n");
}
