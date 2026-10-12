<?php

declare(strict_types=1);

use App\Actions\Notifications\SendTelegramNotification;
use App\Actions\Requests\CreatePartnerRequest;
use App\Data\Requests\NewRequestData;
use App\Enums\DutyStatus;
use App\Enums\Gender;
use App\Enums\NotificationKind;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Duty;
use App\Models\DutyConfirmation;
use App\Models\DutyReminderBatch;
use App\Models\NotificationDelivery;
use App\Models\PartnerRequest;
use App\Models\TelegramAccount;
use App\Services\Notifications\DeliveryContent;
use App\Services\Telegram\BotClient;
use App\Services\Telegram\KeyboardFactory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\AgreementScenario;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-14 09:00:00', 'Europe/Minsk'));
});
afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('escapes personal text and names in a formatted request notification', function (): void {
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['name' => 'Аня <b>&'])->save();
    app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Купи <i>хлеб</i> & молоко', CarbonImmutable::now()->addDays(3)));

    $text = app(DeliveryContent::class)->getText(NotificationDelivery::query()->sole());

    expect($text)->toContain('📝 <b>Аня &lt;b&gt;&amp; просит</b>', 'Купи &lt;i&gt;хлеб&lt;/i&gt; &amp; молоко', '🕒 до сб, 17.10, 09:00')->not->toContain('<i>хлеб</i>');
});

it('tells the reviewer who reported a duty and what confirmation will award', function (Gender $gender, string $verb): void {
    $pair = AgreementScenario::create();
    $pair->first->forceFill(['name' => 'Саша', 'gender' => $gender])->save();
    $reviewer = TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    $source = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->second->id, 'recipient_id' => $pair->first->id, 'status' => RequestStatus::Accepted]);
    $duty = Duty::factory()->create(['partner_request_id' => $source->id, 'status' => DutyStatus::Reported, 'description' => 'Забрать посылку', 'due_at' => CarbonImmutable::now()->addHours(9)->utc(), 'timezone' => 'Europe/Minsk', 'is_reward_eligible' => true, 'reward' => 7]);
    $event = DutyConfirmation::factory()->create(['duty_id' => $duty->id, 'stage' => 'report', 'answer' => true]);
    $delivery = NotificationDelivery::factory()->create(['couple_id' => $pair->couple->id, 'recipient_id' => $pair->second->id, 'kind' => NotificationKind::DutyEvent, 'source_id' => $event->id]);

    expect(app(DeliveryContent::class)->getText($delivery))->toBe(sprintf("🔎 <b>Саша %s: сделано</b>\nЗабрать посылку\n🕒 срок — сегодня, 18:00\n\nПроверьте результат. После подтверждения Саша получит до 7 ♥", $verb));
    $keyboard = DB::transaction(fn () => app(KeyboardFactory::class)->create($delivery, $reviewer));
    expect(array_column($keyboard['inline_keyboard'][0], 'text'))->toBe(['✅ Подтверждаю', '⚠️ Оспорить']);
})->with([[Gender::Male, 'отметил'], [Gender::Female, 'отметила'], [Gender::Unspecified, 'отмечает']]);

it('groups the duty reminder into overdue today and later', function (): void {
    $pair = AgreementScenario::create();
    $now = CarbonImmutable::now('Europe/Minsk');
    foreach ([['Вынести мусор', $now->subDay()->setTime(21, 0)], ['Приготовить ужин', $now->setTime(19, 30)], ['Записаться к врачу', $now->addDays(2)->setTime(12, 0)]] as [$description, $dueAt]) {
        $source = PartnerRequest::factory()->create(['couple_id' => $pair->couple->id, 'author_id' => $pair->second->id, 'recipient_id' => $pair->first->id, 'status' => RequestStatus::Accepted]);
        Duty::factory()->create(['partner_request_id' => $source->id, 'status' => DutyStatus::Pending, 'description' => $description, 'due_at' => $dueAt->utc(), 'timezone' => 'Europe/Minsk']);
    }
    $batch = DutyReminderBatch::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'scheduled_at' => $now->subMinute()->utc()]);
    $delivery = NotificationDelivery::factory()->create(['couple_id' => $pair->couple->id, 'recipient_id' => $pair->first->id, 'kind' => NotificationKind::DutyReminder, 'source_id' => $batch->id]);

    expect(app(DeliveryContent::class)->getText($delivery))->toBe("📋 <b>Ваши дела: 3</b>\n\n⚠️ <b>Просрочено</b>\n• Вынести мусор — вчера, 21:00\n\n<b>Сегодня</b>\n• Приготовить ужин — 19:30\n\n<b>Позже</b>\n• Записаться к врачу — пт, 16.10, 12:00");
});

it('sends notifications as Telegram HTML while plain bot replies stay unformatted', function (): void {
    config(['services.telegram.bot_token' => '123456:test-token', 'services.telegram.enabled' => true]);
    $payloads = [];
    Http::fake(['*/sendMessage' => function (Request $request) use (&$payloads) {
        $payloads[] = $request->data();

        return Http::response(['ok' => true, 'result' => ['message_id' => 31]]);
    }]);
    $pair = AgreementScenario::create();
    TelegramAccount::factory()->create(['user_id' => $pair->second->id]);
    app(CreatePartnerRequest::class)->execute($pair->first, $pair->couple, new NewRequestData(RequestType::Service, 'Принеси чай', CarbonImmutable::now()->addDay()));

    app(SendTelegramNotification::class)->execute(NotificationDelivery::query()->sole()->id);
    app(BotClient::class)->sendMessage('123456', 'Обычный ответ <без разметки>');

    expect($payloads)->toHaveCount(2)->and($payloads[0]['parse_mode'])->toBe('HTML')->and($payloads[0]['text'])->toContain('<b>')
        ->and($payloads[0]['reply_markup']['inline_keyboard'][0])->toHaveCount(2)->and($payloads[1])->not->toHaveKey('parse_mode');
});
