<?php

declare(strict_types=1);

use App\Actions\Admin\UpdateEconomySettings;
use App\Actions\Auth\Telegram\ConsumeTelegramLinkCode;
use App\Actions\Catalogs\CreateCar;
use App\Actions\Couples\Agreements\AcceptProposal;
use App\Actions\Couples\Agreements\ProposeWeeklyLimit;
use App\Actions\Couples\Agreements\SubmitAnswer;
use App\Actions\Couples\EndCouple;
use App\Actions\Couples\UpdateRelationshipDates;
use App\Actions\Dates\CreateDateInvitation;
use App\Actions\Dishes\SaveDish;
use App\Actions\Duties\CheckOverdueDuty;
use App\Actions\Duties\ManageChoreDraw;
use App\Actions\Duties\ReportDuty;
use App\Actions\Duties\VerifyDuty;
use App\Actions\Friends\ArchiveFriend;
use App\Actions\Friends\SaveFriend;
use App\Actions\Hearts\AdjustPartnerHearts;
use App\Actions\Hearts\ReconcileRecordedBalance;
use App\Actions\Money\CreateMoneyRequest;
use App\Actions\Mood\RecordMood;
use App\Actions\Mood\RequestPartnerMood;
use App\Actions\Movies\ManageMovieChoice;
use App\Actions\Movies\VoteForMovie;
use App\Actions\Reminders\CancelReminder;
use App\Actions\Reminders\CreateReminder;
use App\Actions\Reminders\PrepareReminder;
use App\Actions\Requests\CancelPartnerRequest;
use App\Actions\Requests\CreateGuestVisit;
use App\Actions\Requests\CreatePartnerRequest;
use App\Actions\Requests\RatePartnerAct;
use App\Actions\Requests\ResolveCondition;
use App\Actions\Requests\RespondToRequest;
use App\Data\Admin\EconomySettingsData;
use App\Data\Catalogs\CarData;
use App\Data\Couples\RelationshipDatesData;
use App\Data\Couples\WeeklyLimitData;
use App\Data\Dates\DateInvitationData;
use App\Data\Dishes\DishData;
use App\Data\Friends\FriendData;
use App\Data\Hearts\AdjustmentData;
use App\Data\Hearts\BalanceReconciliationData;
use App\Data\Money\MoneyRequestData;
use App\Data\Mood\MoodData;
use App\Data\Reminders\ReminderData;
use App\Data\Requests\ActRatingData;
use App\Data\Requests\GuestVisitData;
use App\Data\Requests\NewRequestData;
use App\Data\Requests\RequestResponseData;
use App\Data\Telegram\IncomingUpdate;
use App\Enums\ConditionType;
use App\Enums\Currency;
use App\Enums\DeclineReason;
use App\Enums\DishKind;
use App\Enums\HeartOperationType;
use App\Enums\InvitationKind;
use App\Enums\MoodLevel;
use App\Enums\RequestResponse;
use App\Enums\RequestType;
use App\Models\AgreementProposal;
use App\Models\ChoreDraw;
use App\Models\Couple;
use App\Models\Dish;
use App\Models\Duty;
use App\Models\Friend;
use App\Models\Movie;
use App\Models\MovieChoice;
use App\Models\NotificationDelivery;
use App\Models\PartnerReminder;
use App\Models\PartnerRequest;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\Hearts\EconomySettingsStore;
use App\Services\Hearts\Ledger;
use App\Services\Notifications\DeliveryLifecycle;
use App\Services\Realtime\SelectionPresence;
use App\Services\Scheduling\DueCare;
use App\Services\Telegram\WebhookHandler;
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
if ($operation === 'care_process') {
    Date::setTestNow(CarbonImmutable::createFromTimestampUTC($limit));
}
if (str_ends_with($operation, '_late')) {
    Date::setTestNow(CarbonImmutable::now()->addDays(5));
}
if (in_array($operation, ['selection_draw', 'chore_start'], true)) {
    $selection = $operation === 'selection_draw' ? MovieChoice::query()->findOrFail($value) : ChoreDraw::query()->findOrFail($value);
    foreach ([$selection->first_user_id, $selection->second_user_id] as $participantId) {
        $app->make(SelectionPresence::class)->touch(User::query()->findOrFail($participantId), $selection);
    }
}
if ($operation === 'wallet_reconciliation') {
    config(['admin_panel.login' => $actor->login, 'admin_panel.password_hash' => 'test-reconciliation-owner']);
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
        'wallet_reconciliation' => $app->make(ReconcileRecordedBalance::class)->execute($actor, User::query()->findOrFail($value), new BalanceReconciliationData($limit, 10, sprintf('00000000-0000-4000-8000-%012d', $value), 'Concurrent reconciliation')),
        'act_rate' => $app->make(RatePartnerAct::class)->execute($actor, $couple, PartnerRequest::query()->findOrFail($value), new ActRatingData(0, $limit, null)),
        'selection_create' => $app->make(ManageMovieChoice::class)->create($actor, $couple, 'swipe', sprintf('00000000-0000-4000-8000-%012d', $value)),
        'chore_create' => $app->make(ManageChoreDraw::class)->create($actor, $couple, 'Concurrent chore', CarbonImmutable::createFromTimestampUTC($limit), sprintf('00000000-0000-4000-8000-%012d', $value)),
        'selection_answer' => $app->make(ManageMovieChoice::class)->answer($actor, $couple, MovieChoice::query()->findOrFail($value), $limit === 1),
        'selection_vote' => $app->make(ManageMovieChoice::class)->vote($actor, $couple, MovieChoice::query()->findOrFail($value), Movie::query()->findOrFail($limit), true),
        'selection_draw' => $app->make(ManageMovieChoice::class)->draw($actor, $couple, MovieChoice::query()->findOrFail($value)),
        'chore_start' => $app->make(ManageChoreDraw::class)->start($actor, $couple, ChoreDraw::query()->findOrFail($value)),
        'chore_answer' => $app->make(ManageChoreDraw::class)->answer($actor, $couple, ChoreDraw::query()->findOrFail($value), $limit === 1),
        'movie_vote' => $app->make(VoteForMovie::class)->execute($actor, $couple, Movie::query()->findOrFail($value), $limit === 1),
        'car_create' => $app->make(CreateCar::class)->execute($actor, new CarData('Race car', 'petrol', sprintf('00000000-0000-4000-8000-%012d', $value))),
        'relationship_dates' => $app->make(UpdateRelationshipDates::class)->execute($actor, $couple, new RelationshipDatesData(sprintf('%d-01-01', $value), null, $limit)),
        'telegram_code' => $app->make(ConsumeTelegramLinkCode::class)->execute($actor, sprintf('00000000-0000-4000-8000-%012d', $value)),
        'telegram_callback' => $app->make(WebhookHandler::class)->execute(new IncomingUpdate($limit, TelegramAccount::query()->where('user_id', $actor->id)->sole()->telegram_id, '', null, sprintf('act:%s', str_pad((string) $value, 40, 'x')), null), hash('sha256', (string) $limit)),
        'notification_claim' => $app->make(DeliveryLifecycle::class)->claim(NotificationDelivery::query()->findOrFail($value)) ?? throw ValidationException::withMessages(['notification' => 'Unavailable']),
        'care_process' => $app->make(DueCare::class)->execute(),
        'mood_partner_request' => $app->make(RequestPartnerMood::class)->execute($actor, $couple),
        'mood_record' => $app->make(RecordMood::class)->execute($actor, $couple, new MoodData(MoodLevel::Good, sprintf('00000000-0000-4000-8000-%012d', $value))),
        'mood_prompt' => $app->make(RecordMood::class)->execute($actor, $couple, new MoodData(MoodLevel::Good, sprintf('00000000-0000-4000-8000-%012d', $limit), promptId: $value)),
        'reminder_create' => $app->make(CreateReminder::class)->execute($actor, $couple, new ReminderData('Race reminder', CarbonImmutable::tomorrow(), sprintf('00000000-0000-4000-8000-%012d', $value))),
        'reminder_cancel' => $app->make(CancelReminder::class)->execute($actor, PartnerReminder::query()->findOrFail($value), $limit),
        'reminder_prepare_late' => $app->make(PrepareReminder::class)->execute(PartnerReminder::query()->findOrFail($value), $limit),
        'friend_edit' => $app->make(SaveFriend::class)->execute($actor, new FriendData(sprintf('Friend %d', $limit), sprintf('00000000-0000-4000-8000-%012d', $limit)), Friend::query()->findOrFail($value)),
        'friend_archive' => $app->make(ArchiveFriend::class)->execute($actor, Friend::query()->findOrFail($value), 0),
        'visit_create' => $app->make(CreateGuestVisit::class)->execute($actor, $couple, new GuestVisitData([['id' => $value, 'version' => 0]], CarbonImmutable::tomorrow(), 60, 'Race visit', sprintf('00000000-0000-4000-8000-%012d', $limit))),
        'date_create' => $app->make(CreateDateInvitation::class)->execute($actor, $couple, new DateInvitationData(InvitationKind::Date, 'Race date', CarbonImmutable::tomorrow(), null, null, sprintf('00000000-0000-4000-8000-%012d', $value))),
        'money_create' => $app->make(CreateMoneyRequest::class)->execute($actor, $couple, new MoneyRequestData('12.30', Currency::Byn, 'Race', CarbonImmutable::tomorrow(), sprintf('00000000-0000-4000-8000-%012d', $value))),
        'dish_edit' => $app->make(SaveDish::class)->execute($actor, new DishData(sprintf('Edit %d', $limit), 'Ingredient', '9.99', Currency::Byn, DishKind::Ordinary, 1, 0, sprintf('00000000-0000-4000-8000-%012d', $limit)), Dish::query()->findOrFail($value)),
        'economy_edit' => $app->make(UpdateEconomySettings::class)->execute($actor, new EconomySettingsData($value, array_replace($app->make(EconomySettingsStore::class)->initialize()->settings, ['reward' => $limit]))),
        'request_cancel' => $app->make(CancelPartnerRequest::class)->execute($actor, $couple, PartnerRequest::query()->findOrFail($value), $limit),
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
