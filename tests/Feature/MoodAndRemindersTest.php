<?php

declare(strict_types=1);
use App\Actions\Couples\EndCouple;
use App\Actions\Mood\CreateMoodPrompt;
use App\Actions\Mood\RecordMood;
use App\Actions\Mood\SetMoodSchedule;
use App\Actions\Reminders\CancelReminder;
use App\Actions\Reminders\CreateReminder;
use App\Actions\Reminders\PrepareReminder;
use App\Data\Mood\MoodData;
use App\Data\Reminders\ReminderData;
use App\Enums\HeartOperationType;
use App\Enums\MoodLevel;
use App\Enums\MoodSource;
use App\Enums\ReminderStatus;
use App\Models\MoodEntry;
use App\Models\MoodPrompt;
use App\Models\PartnerReminder;
use App\Models\User;
use App\Services\Hearts\Ledger;
use App\Services\Mood\MoodHistory;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('records immutable moods with exact retries and shows the latest on the home page', function (): void {
    $pair = AgreementScenario::create();
    $data = new MoodData(MoodLevel::Good, Str::uuid()->toString());
    $entry = app(RecordMood::class)->execute($pair->first, $pair->couple, $data);
    expect(app(RecordMood::class)->execute($pair->first, $pair->couple, $data)->id)->toBe($entry->id);
    expect(fn () => app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Bad, $data->operationKey)))->toThrow(ValidationException::class);
    $new = app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Great, Str::uuid()->toString()));
    expect(MoodEntry::query()->count())->toBe(2);
    expect($entry->refresh()->level)->toBe(MoodLevel::Good);
    expect(app(MoodHistory::class)->getLatest($pair->couple)->get($pair->first->id)->id)->toBe($new->id);
    $this->asUser($pair->second)->get('/')->assertOk()->assertSee('Отлично')->assertSee($pair->first->name);
});
it('validates web mood choices and always uses the authenticated author and web source', function (): void {
    $pair = AgreementScenario::create();
    $payload = ['level' => 'great', 'operation_key' => Str::uuid()->toString(), 'user_id' => $pair->second->id, 'source' => 'telegram'];
    $this->asUser($pair->first)->post(route('mood.store', $pair->couple), [...$payload, 'level' => 'unknown'])->assertSessionHasErrors('level');
    $this->post(route('mood.store', $pair->couple), $payload)->assertRedirect();
    expect(MoodEntry::query()->sole()->user_id)->toBe($pair->first->id);
    expect(MoodEntry::query()->sole()->source)->toBe(MoodSource::Web);
});
it('keeps permanent mood history and isolates couples including administrators', function (): void {
    $pair = AgreementScenario::create();
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'level' => MoodLevel::Bad, 'recorded_at' => CarbonImmutable::now()->subDays(31)]);
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'level' => MoodLevel::Great]);
    $other = AgreementScenario::create();
    MoodEntry::factory()->create(['couple_id' => $other->couple->id, 'user_id' => $other->first->id, 'level' => MoodLevel::Low]);
    $this->asUser($pair->second)->get(route('mood.index', $pair->couple))->assertOk()->assertSee('Отлично')->assertDontSee($other->first->name)->assertViewHas('entries', fn (LengthAwarePaginator $entries): bool => $entries->total() === 2);
    $this->asUser(User::factory()->create(['is_admin' => true]))->get(route('mood.index', $pair->couple))->assertForbidden();
    expect(fn () => app(RecordMood::class)->execute($other->first, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString())))->toThrow(AuthorizationException::class);
});
it('keeps mood schedule personal with confirmation stale version protection and manual recording while disabled', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->put(route('mood.schedule', $pair->couple), ['enabled' => 0, 'expected_version' => 0])->assertSessionHasErrors('confirmation');
    app(SetMoodSchedule::class)->execute($pair->first, $pair->couple, false, 0);
    app(SetMoodSchedule::class)->execute($pair->first, $pair->couple, false, 0);
    expect($pair->couple->members()->where('user_id', $pair->first->id)->sole()->mood_schedule_enabled)->toBeFalse();
    expect($pair->couple->members()->where('user_id', $pair->second->id)->sole()->mood_schedule_enabled)->toBeTrue();
    expect(fn () => app(SetMoodSchedule::class)->execute($pair->first, $pair->couple, true, 0))->toThrow(ValidationException::class);
    app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Low, Str::uuid()->toString()));
    expect(MoodEntry::query()->count())->toBe(1);
});
it('creates scheduled mood prompts in the couple timezone once and answers them once through the shared action', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 06:30:00', 'UTC'));
    $pair = AgreementScenario::create();
    $scheduled = CarbonImmutable::parse('2026-10-11 06:00:00', 'UTC');
    $pair->couple->members()->update(['joined_at' => $scheduled->subDay()]);
    $prompt = app(CreateMoodPrompt::class)->execute($pair->first, $pair->couple, $scheduled);
    expect($prompt)->not->toBeNull();
    expect(app(CreateMoodPrompt::class)->execute($pair->first, $pair->couple, $scheduled)->id)->toBe($prompt->id);
    expect(app(CreateMoodPrompt::class)->execute($pair->first, $pair->couple, $scheduled->addHour()))->toBeNull();
    $data = new MoodData(MoodLevel::Good, Str::uuid()->toString(), MoodSource::Telegram, $prompt->id);
    $entry = app(RecordMood::class)->execute($pair->first, $pair->couple, $data);
    expect(app(RecordMood::class)->execute($pair->first, $pair->couple, $data)->id)->toBe($entry->id);
    expect($prompt->refresh()->answered_at)->not->toBeNull();
    expect(fn () => app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Bad, Str::uuid()->toString(), promptId: $prompt->id)))->toThrow(ValidationException::class);
});
it('rejects unavailable mood prompts without recording an answer', function (string $case): void {
    $pair = AgreementScenario::create();
    $prompt = MoodPrompt::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $case === 'foreign' ? $pair->second->id : $pair->first->id, 'scheduled_at' => CarbonImmutable::now()->subHour(), 'expires_at' => $case === 'expired' ? CarbonImmutable::now() : CarbonImmutable::now()->addHour()]);
    if ($case === 'outdated') {
        app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Great, Str::uuid()->toString()));
    }
    if ($case === 'disabled') {
        app(SetMoodSchedule::class)->execute($pair->first, $pair->couple, false, 0);
        app(SetMoodSchedule::class)->execute($pair->first, $pair->couple, true, 1);
    }
    $count = MoodEntry::query()->count();
    expect(fn () => app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Bad, Str::uuid()->toString(), promptId: $prompt->id)))->toThrow(ValidationException::class);
    expect(MoodEntry::query()->count())->toBe($count);
    expect($prompt->refresh()->answered_at)->toBeNull();
})->with(['foreign', 'expired', 'outdated', 'disabled']);
it('creates a confirmed reminder for the partner with timezone conversion and safe text output', function (): void {
    $pair = AgreementScenario::create();
    $date = CarbonImmutable::now()->addDays(2)->setTimezone($pair->couple->timezone)->format('Y-m-d\TH:i');
    $payload = ['text' => '<script>private reminder</script>', 'due_at' => $date, 'operation_key' => Str::uuid()->toString()];
    $this->asUser($pair->first)->get(route('reminders.create', $pair->couple))->assertOk();
    $this->post(route('reminders.store', $pair->couple), $payload)->assertSessionHasErrors('confirmation');
    $this->post(route('reminders.store', $pair->couple), [...$payload, 'confirmation' => 1, 'recipient_id' => $pair->first->id])->assertSessionHasErrors('recipient_id');
    $this->post(route('reminders.store', $pair->couple), [...$payload, 'confirmation' => 1])->assertRedirect();
    $reminder = PartnerReminder::query()->sole();
    expect($reminder->recipient_id)->toBe($pair->second->id);
    expect($reminder->due_at->format('Y-m-d H:i'))->toBe(CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $date, $pair->couple->timezone)->utc()->format('Y-m-d H:i'));
    $this->asUser($pair->second)->get(route('reminders.index', $pair->couple))->assertOk()->assertSee('&lt;script&gt;private reminder&lt;/script&gt;', false)->assertDontSee('<script>private reminder</script>', false);
});
it('rejects past reminder dates and preserves exact retries after cancellation', function (): void {
    $pair = AgreementScenario::create();
    expect(fn () => app(CreateReminder::class)->execute($pair->first, $pair->couple, new ReminderData('Встреча', CarbonImmutable::yesterday(), Str::uuid()->toString())))->toThrow(ValidationException::class);
    $data = new ReminderData('Встреча', CarbonImmutable::tomorrow(), Str::uuid()->toString());
    $reminder = app(CreateReminder::class)->execute($pair->first, $pair->couple, $data);
    app(CancelReminder::class)->execute($pair->first, $reminder, 0);
    expect(app(CreateReminder::class)->execute($pair->first, $pair->couple, $data)->id)->toBe($reminder->id);
    expect($reminder->refresh()->status)->toBe(ReminderStatus::Cancelled);
    expect(fn () => app(CreateReminder::class)->execute($pair->first, $pair->couple, new ReminderData('Изменено', $data->dueAt, $data->operationKey)))->toThrow(ValidationException::class);
    expect(PartnerReminder::query()->count())->toBe(1);
});
it('allows cancellation only by the author with confirmation and prevents stale preparation', function (): void {
    $pair = AgreementScenario::create();
    $reminder = app(CreateReminder::class)->execute($pair->first, $pair->couple, new ReminderData('Встреча', CarbonImmutable::tomorrow(), Str::uuid()->toString()));
    expect(fn () => app(CancelReminder::class)->execute($pair->second, $reminder, 0))->toThrow(AuthorizationException::class);
    $this->asUser($pair->first)->delete(route('reminders.destroy', [$pair->couple, $reminder]), ['expected_version' => 0])->assertSessionHasErrors('confirmation');
    $this->delete(route('reminders.destroy', [$pair->couple, $reminder]), ['expected_version' => 0, 'confirmation' => 1])->assertRedirect();
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addDays(2));
    app(PrepareReminder::class)->execute($reminder, 0);
    expect($reminder->refresh()->status)->toBe(ReminderStatus::Cancelled);
});
it('prepares reminders once without claiming delivery and expires delays greater than a day', function (int $hours, ReminderStatus $status): void {
    $pair = AgreementScenario::create();
    $due = CarbonImmutable::now()->setMicrosecond(0)->addHour();
    $reminder = app(CreateReminder::class)->execute($pair->first, $pair->couple, new ReminderData('Встреча', $due, Str::uuid()->toString()));
    expect(app(PrepareReminder::class)->execute($reminder, 0)->status)->toBe(ReminderStatus::Scheduled);
    CarbonImmutable::setTestNow($due->addHours($hours));
    app(PrepareReminder::class)->execute($reminder, 0);
    app(PrepareReminder::class)->execute($reminder, 0);
    expect($reminder->refresh()->status)->toBe($status);
    expect($reminder->version)->toBe(1);
    if ($status === ReminderStatus::Ready) {
        expect(fn () => app(CancelReminder::class)->execute($pair->first, $reminder, 0))->toThrow(ValidationException::class);
        app(CancelReminder::class)->execute($pair->first, $reminder, 1);
        expect($reminder->refresh()->status)->toBe(ReminderStatus::Cancelled);
    }
})->with([[0, ReminderStatus::Ready], [24, ReminderStatus::Ready], [25, ReminderStatus::Expired]]);
it('closes new actions after ending a couple and cancels pending delivery without exposing history to outsiders', function (): void {
    $pair = AgreementScenario::create();
    $reminder = app(CreateReminder::class)->execute($pair->first, $pair->couple, new ReminderData('Встреча', CarbonImmutable::now()->addHour(), Str::uuid()->toString()));
    app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString()));
    app(EndCouple::class)->execute($pair->first, $pair->couple);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addHours(2));
    app(PrepareReminder::class)->execute($reminder, 0);
    expect($reminder->refresh()->status)->toBe(ReminderStatus::Cancelled);
    expect(fn () => app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString())))->toThrow(AuthorizationException::class);
    $this->asUser($pair->second)->get(route('reminders.index', $pair->couple))->assertOk()->assertSee('Встреча');
    $this->asUser(User::factory()->create(['is_admin' => true]))->get(route('reminders.index', $pair->couple))->assertForbidden();
});

it('allows ordinary moods and reminders at negative balance without changing the wallet', function (): void {
    $pair = AgreementScenario::create();
    app(Ledger::class)->apply($pair->first, -30, HeartOperationType::FalseReportPenalty, 'care:negative');
    app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Low, Str::uuid()->toString()));
    app(CreateReminder::class)->execute($pair->first, $pair->couple, new ReminderData('Встреча', CarbonImmutable::tomorrow(), Str::uuid()->toString()));
    expect(MoodEntry::query()->count())->toBe(1);
    expect(PartnerReminder::query()->count())->toBe(1);
});
it('blocks outsiders from creating and cancelling reminders and refuses cancellation after delivery', function (): void {
    $pair = AgreementScenario::create();
    $outsider = User::factory()->create(['is_admin' => true]);
    expect(fn () => app(CreateReminder::class)->execute($outsider, $pair->couple, new ReminderData('Встреча', CarbonImmutable::tomorrow(), Str::uuid()->toString())))->toThrow(AuthorizationException::class);
    $reminder = PartnerReminder::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->first->id, 'recipient_id' => $pair->second->id, 'status' => ReminderStatus::Delivered]);
    expect(fn () => app(CancelReminder::class)->execute($outsider, $reminder, 0))->toThrow(AuthorizationException::class);
    expect(fn () => app(CancelReminder::class)->execute($pair->first, $reminder, 0))->toThrow(ValidationException::class);
    expect($reminder->refresh()->status)->toBe(ReminderStatus::Delivered);
});
it('reports nonexistent local reminder times against the reminder date field', function (): void {
    $pair = AgreementScenario::create();
    $pair->couple->forceFill(['timezone' => 'Europe/Berlin'])->save();
    $this->asUser($pair->first)->post(route('reminders.store', $pair->couple), ['text' => 'Встреча', 'due_at' => '2027-03-28T02:30', 'operation_key' => Str::uuid()->toString(), 'confirmation' => 1])->assertSessionHasErrors('due_at');
    expect(PartnerReminder::query()->count())->toBe(0);
});

it('does not expose home page moods to an invalidated password session', function (): void {
    $pair = AgreementScenario::create();
    app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Great, Str::uuid()->toString()));
    $this->asUser($pair->second)->withSession(['password_hash_web' => $pair->second->password]);
    $pair->second->forceFill(['password' => 'Changed-test-password-123'])->save();
    $this->get(route('home'))->assertRedirect(route('login'))->assertDontSee($pair->first->name);
});

it('accepts neutral mood from the website and shows it to the partner', function (): void {
    $pair = AgreementScenario::create();
    $this->asUser($pair->first)->get(route('mood.index', $pair->couple))->assertOk()->assertSee('Нейтрально');
    $this->post(route('mood.store', $pair->couple), ['operation_key' => Str::uuid()->toString(), 'level' => 'neutral'])->assertSessionHasNoErrors()->assertRedirect();
    expect(MoodEntry::query()->sole()->level)->toBe(MoodLevel::Neutral);
    $this->asUser($pair->second)->get(route('home'))->assertOk()->assertSee('Нейтрально');
});
