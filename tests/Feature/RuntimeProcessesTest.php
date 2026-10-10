<?php

declare(strict_types=1);

use App\Actions\Reminders\CreateReminder;
use App\Data\Reminders\ReminderData;
use App\Enums\DeliveryStatus;
use App\Enums\ReminderStatus;
use App\Models\NotificationDelivery;
use App\Models\TelegramAccount;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-11 04:00:00', 'UTC'));
    config([
        'queue.default' => 'database',
        'services.telegram.enabled' => true,
        'services.telegram.bot_token' => '123456789:fake-test-token',
    ]);
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 42]])]);
    $this->pair = AgreementScenario::create();
    TelegramAccount::factory()->create(['user_id' => $this->pair->second->id]);
    $this->reminder = app(CreateReminder::class)->execute($this->pair->first, $this->pair->couple, new ReminderData('Тест расписания', CarbonImmutable::now()->addMinute(), Str::uuid()->toString()));
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinute());
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('processes a scheduled reminder through the database queue worker without duplicate delivery', function (): void {
    $this->artisan('care:process-due')->assertSuccessful();
    $this->artisan('care:process-due')->assertSuccessful();
    $this->artisan('telegram:dispatch-notifications')->assertSuccessful();
    expect(DB::table('jobs')->count())->toBe(1);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'telegram,default', '--once' => true])->assertSuccessful();
    $this->artisan('telegram:dispatch-notifications')->assertSuccessful();
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'telegram,default', '--stop-when-empty' => true])->assertSuccessful();
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Delivered);
    expect(NotificationDelivery::query()->sole()->status)->toBe(DeliveryStatus::Sent);
    expect(DB::table('jobs')->count())->toBe(0);
    expect(DB::table('failed_jobs')->count())->toBe(0);
    Http::assertSentCount(1);
});

it('keeps a queued reminder pending when the bot is disabled and recovers after enabling it', function (): void {
    $this->artisan('care:process-due')->assertSuccessful();
    config(['services.telegram.enabled' => false]);
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'telegram,default', '--once' => true])->assertSuccessful();
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Ready);
    expect(NotificationDelivery::query()->sole()->status)->toBe(DeliveryStatus::Pending);
    Http::assertNothingSent();
    config(['services.telegram.enabled' => true]);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(6));
    $this->artisan('telegram:dispatch-notifications')->assertSuccessful();
    $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'telegram,default', '--once' => true])->assertSuccessful();
    expect($this->reminder->refresh()->status)->toBe(ReminderStatus::Delivered);
    expect(DB::table('jobs')->count())->toBe(0);
    Http::assertSentCount(1);
});
