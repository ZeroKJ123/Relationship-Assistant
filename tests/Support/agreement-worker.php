<?php

declare(strict_types=1);

use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Couples\Agreements\ProposeWeeklyLimit;
use App\Actions\Couples\Agreements\SubmitAnswer;
use App\Actions\Couples\EndCouple;
use App\Actions\Duties\CheckOverdueDuty;
use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Hearts\AdjustPartnerHearts;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Couples\WeeklyLimitData;
use App\Data\Hearts\AdjustmentData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Enums\ConditionType;
use App\Enums\DeclineReason;
use App\Enums\HeartOperationType;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\AgreementProposal;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\Ledger;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Date;
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
if (str_ends_with($operation, '_late')) {
    Date::setTestNow(CarbonImmutable::now()->addDays(5));
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
        'duty_report', 'duty_report_late' => $app->make(ReportDuty::class)->execute($actor, $couple, Duty::query()->findOrFail($value), version: $limit, isDone: true),
        'duty_verify' => $app->make(VerifyDuty::class)->execute($actor, $couple, Duty::query()->findOrFail($value), version: $limit, isConfirmed: true),
        'duty_dispute' => $app->make(VerifyDuty::class)->execute($actor, $couple, Duty::query()->findOrFail($value), version: $limit, isConfirmed: false),
        'duty_overdue_late' => $app->make(CheckOverdueDuty::class)->execute(Duty::query()->findOrFail($value)),
        'wallet_credit' => $app->make(Ledger::class)->apply($actor, $limit, HeartOperationType::Reward, sprintf('race:user:%d:%d', $actor->id, $value)),
        'wallet_debit' => $app->make(Ledger::class)->apply($actor, -$limit, HeartOperationType::FalseReportPenalty, sprintf('race:user:%d:%d', $actor->id, $value)),
        'manual_thanks' => $app->make(AdjustPartnerHearts::class)->execute($actor, $couple, new AdjustmentData(HeartOperationType::ManualThanks, $limit, 'Test reason', sprintf('00000000-0000-4000-8000-%012d', $value))),
        'very_wanted' => $app->make(CreatePartnerRequest::class)->execute($actor, $couple, new NewRequestData(type: RequestType::Food, description: 'Test request', dueAt: CarbonImmutable::tomorrow(), isVeryWanted: true, operationKey: sprintf('00000000-0000-4000-8000-%012d', $value))),
        'request_create' => $app->make(CreatePartnerRequest::class)->execute($actor, $couple, new NewRequestData(type: RequestType::Service, description: 'Test request', dueAt: CarbonImmutable::tomorrow(), operationKey: sprintf('00000000-0000-4000-8000-%012d', $value))),
        'request_agree' => $app->make(RespondToRequest::class)->execute($actor, $couple, PartnerRequest::query()->findOrFail($value), new RequestResponseData(RequestResponse::Agree, $limit)),
        'request_decline' => $app->make(RespondToRequest::class)->execute($actor, $couple, PartnerRequest::query()->findOrFail($value), new RequestResponseData(RequestResponse::Decline, $limit, DeclineReason::Ordinary)),
        'request_condition' => $app->make(RespondToRequest::class)->execute($actor, $couple, PartnerRequest::query()->findOrFail($value), new RequestResponseData(response: RequestResponse::Conditional, expectedVersion: $limit, reason: null, conditionType: ConditionType::Action, description: 'Test condition', dueAt: CarbonImmutable::tomorrow())),
        'request_accept_condition' => $app->make(ResolveCondition::class)->execute($actor, $couple, PartnerRequest::query()->findOrFail($value), $limit),
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
