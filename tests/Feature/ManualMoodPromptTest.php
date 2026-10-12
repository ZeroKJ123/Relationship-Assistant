<?php

declare(strict_types=1);

use App\Actions\Couples\EndCouple;
use App\Actions\Mood\RecordMood;
use App\Actions\Mood\RequestPartnerMood;
use App\Actions\Mood\SetMoodSchedule;
use App\Data\Mood\MoodData;
use App\Enums\MoodLevel;
use App\Enums\MoodSource;
use App\Enums\NotificationKind;
use App\Enums\TelegramOperation;
use App\Models\MoodPrompt;
use App\Models\NotificationDelivery;
use App\Models\TelegramCallbackAction;
use App\Models\User;
use App\Services\Notifications\DeliveryContent;
use App\Services\Telegram\CallbackOperations;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-10 14:30:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('asks only the partner and reuses the pending request without duplicate delivery', function (): void {
    $pair = AgreementScenario::create();
    app(SetMoodSchedule::class)->execute($pair->second, $pair->couple, false, 0);
    $this->asUser($pair->first)->post(route('mood.prompts.store', $pair->couple), ['user_id' => $pair->first->id])->assertRedirect(route('home'))->assertSessionHas('toast', 'Запрос отправлен');
    $prompt = MoodPrompt::query()->sole();
    expect($prompt->user_id)->toBe($pair->second->id)->and($prompt->is_manual)->toBeTrue();
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSeconds(RequestPartnerMood::RESEND_COOLDOWN_SECONDS - 1));
    $this->post(route('mood.prompts.store', $pair->couple))->assertRedirect(route('home'))->assertSessionHas('toast', 'Запрос уже отправлен. Повторить можно через минуту.');
    expect(MoodPrompt::query()->count())->toBe(1)->and(NotificationDelivery::query()->where('kind', NotificationKind::MoodPrompt)->count())->toBe(1);
    $delivery = NotificationDelivery::query()->sole();
    expect(app(DeliveryContent::class)->getText($delivery))->toContain(sprintf('%s спрашивает, как вы', e($pair->first->name)))->not->toContain('http');
});

it('resends a manual question at the cooldown boundary and rejects the superseded answer', function (): void {
    $pair = AgreementScenario::create();
    $original = app(RequestPartnerMood::class)->execute($pair->first, $pair->couple);
    CarbonImmutable::setTestNow($original->scheduled_at->addSeconds(RequestPartnerMood::RESEND_COOLDOWN_SECONDS));
    $replacement = app(RequestPartnerMood::class)->execute($pair->first, $pair->couple);
    expect($replacement->id)->not->toBe($original->id);
    expect($original->refresh()->expires_at->lessThanOrEqualTo(now()))->toBeTrue();
    expect(app(DeliveryContent::class)->getText(NotificationDelivery::query()->where('source_id', $original->id)->sole()))->toBeNull();
    expect(fn () => app(RecordMood::class)->execute($pair->second, $pair->couple, new MoodData(MoodLevel::Neutral, Str::uuid()->toString(), promptId: $original->id)))->toThrow(ValidationException::class);
    expect(NotificationDelivery::query()->count())->toBe(2);
    expect(app(RequestPartnerMood::class)->execute($pair->first, $pair->couple)->id)->toBe($replacement->id);
});

it('shows and answers a manual request on the website with the schedule disabled', function (): void {
    $pair = AgreementScenario::create();
    $prompt = app(RequestPartnerMood::class)->execute($pair->first, $pair->couple);
    app(SetMoodSchedule::class)->execute($pair->second, $pair->couple, false, 0);
    expect($prompt->refresh()->expires_at->isFuture())->toBeTrue();
    $this->asUser($pair->second)->get(route('mood.index', $pair->couple))->assertOk()->assertViewHas('prompt', fn ($visible) => $visible->id === $prompt->id);
    $this->post(route('mood.store', $pair->couple), ['level' => 'good', 'prompt_id' => $prompt->id, 'operation_key' => Str::uuid()->toString()])->assertSessionHasNoErrors();
    expect($prompt->refresh()->answered_at)->not->toBeNull();
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSecond());
    expect(app(RequestPartnerMood::class)->execute($pair->first, $pair->couple)->id)->not->toBe($prompt->id);
});

it('answers manual requests using the common Telegram action with the schedule disabled', function (): void {
    $pair = AgreementScenario::create();
    app(SetMoodSchedule::class)->execute($pair->second, $pair->couple, false, 0);
    $prompt = app(RequestPartnerMood::class)->execute($pair->first, $pair->couple);
    $action = new TelegramCallbackAction;
    $action->forceFill(['source_kind' => 'mood', 'source_id' => $prompt->id, 'operation' => TelegramOperation::MoodGreat, 'operation_key' => Str::uuid()->toString()]);
    app(CallbackOperations::class)->requireCurrent($action, $pair->second, $pair->couple);
    app(CallbackOperations::class)->execute($action, $pair->second, $pair->couple);
    expect($prompt->refresh()->answered_at)->not->toBeNull();
});

it('rejects foreign and expired manual answers and suppresses stale Telegram delivery', function (): void {
    $pair = AgreementScenario::create();
    $prompt = app(RequestPartnerMood::class)->execute($pair->first, $pair->couple);
    expect(fn () => app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString(), promptId: $prompt->id)))->toThrow(ValidationException::class);
    CarbonImmutable::setTestNow($prompt->expires_at);
    expect(fn () => app(RecordMood::class)->execute($pair->second, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString(), promptId: $prompt->id)))->toThrow(ValidationException::class);
    expect(app(DeliveryContent::class)->getText(NotificationDelivery::query()->sole()))->toBeNull();
});

it('replaces a manual prompt made stale by a newer independent mood', function (): void {
    $pair = AgreementScenario::create();
    $prompt = app(RequestPartnerMood::class)->execute($pair->first, $pair->couple);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSecond());
    app(RecordMood::class)->execute($pair->second, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString(), MoodSource::Web));
    $this->asUser($pair->second)->get(route('mood.index', $pair->couple))->assertViewHas('prompt', null);
    expect(app(DeliveryContent::class)->getText(NotificationDelivery::query()->where('kind', NotificationKind::MoodPrompt)->sole()))->toBeNull();
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addSecond());
    expect(app(RequestPartnerMood::class)->execute($pair->first, $pair->couple)->id)->not->toBe($prompt->id);
});

it('denies mood requests outside the active pair', function (): void {
    $pair = AgreementScenario::create();
    expect(fn () => app(RequestPartnerMood::class)->execute(User::factory()->create(), $pair->couple))->toThrow(AuthorizationException::class);
    app(EndCouple::class)->execute($pair->first, $pair->couple);
    expect(fn () => app(RequestPartnerMood::class)->execute($pair->first, $pair->couple))->toThrow(AuthorizationException::class);
    expect(MoodPrompt::query()->count())->toBe(0);
});
