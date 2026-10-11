<?php

declare(strict_types=1);

use App\Actions\Mood\RecordMood;
use App\Data\Mood\MoodData;
use App\Data\Telegram\IncomingUpdate;
use App\Enums\MoodLevel;
use App\Enums\MoodStickerFormat;
use App\Enums\MoodStickerStatus;
use App\Jobs\DownloadMoodStickerJob;
use App\Models\MoodEntry;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\Telegram\KeyboardFactory;
use App\Services\Telegram\MoodStickers;
use App\Services\Telegram\WebhookHandler;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\AgreementScenario;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    config(['services.telegram.enabled' => true, 'services.telegram.bot_token' => '123456789:fake-test-token']);
    Storage::fake(MoodStickers::DISK);
});

it('queues a sticker once as a mood reply instead of falling back to login', function (MoodStickerFormat $format): void {
    Queue::fake();
    $pair = AgreementScenario::create();
    $account = TelegramAccount::factory()->create(['user_id' => $pair->first->id]);
    $entry = app(RecordMood::class)->execute($pair->first, $pair->couple, new MoodData(MoodLevel::Good, Str::uuid()->toString()));
    DB::transaction(fn (): array => app(KeyboardFactory::class)->createMoodEmoji($entry, $account));
    $payload = ['update_id' => 123, 'message' => ['from' => ['id' => (int) $account->telegram_id, 'is_bot' => false], 'chat' => ['id' => (int) $account->telegram_id, 'type' => 'private'], 'sticker' => ['file_id' => 'test_sticker', 'is_animated' => $format === MoodStickerFormat::Animation, 'is_video' => $format === MoodStickerFormat::Video]]];
    $update = IncomingUpdate::fromArray($payload);
    expect($update?->sticker?->format)->toBe($format);
    $reply = app(WebhookHandler::class)->execute($update, 'payload-hash');
    expect($reply['text'])->toContain('Стикер принят')->not->toContain('Начните вход');
    expect(app(WebhookHandler::class)->execute($update, 'payload-hash'))->toBe($reply);
    expect($entry->refresh()->sticker_status)->toBe(MoodStickerStatus::Pending);
    Queue::assertPushed(DownloadMoodStickerJob::class, 1);
    Http::assertNothingSent();
})->with([MoodStickerFormat::Image, MoodStickerFormat::Animation, MoodStickerFormat::Video]);

it('downloads supported stickers outside transactions and serves only pair members', function (MoodStickerFormat $format): void {
    $pair = AgreementScenario::create();
    $entry = MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'sticker_status' => MoodStickerStatus::Pending, 'sticker_format' => $format]);
    ob_start();
    $image = imagecreatetruecolor(64, 64);
    imagewebp($image);
    $imageBytes = ob_get_clean();
    $bytes = match ($format) {
        MoodStickerFormat::Image => $imageBytes,
        MoodStickerFormat::Animation => gzencode('{"v":"5.7.4","w":512,"h":512,"fr":30,"ip":0,"op":30,"layers":[],"assets":[]}'),
        MoodStickerFormat::Video => "\x1A\x45\xDF\xA3webm-test-video",
    };
    $extension = $format === MoodStickerFormat::Animation ? 'tgs' : $format->value;
    Http::fake(function (Request $request) use ($bytes, $extension): PromiseInterface {
        expect(DB::transactionLevel())->toBe(0);
        if (str_ends_with($request->url(), '/getFile')) {
            return Http::response(['ok' => true, 'result' => ['file_path' => sprintf('stickers/test.%s', $extension), 'file_size' => strlen($bytes)]]);
        }

        return Http::response($bytes);
    });
    $job = new DownloadMoodStickerJob($entry->id, 'test_sticker', $format);
    app()->call([$job, 'handle']);
    expect($entry->refresh()->sticker_status)->toBe(MoodStickerStatus::Ready);
    Storage::disk(MoodStickers::DISK)->assertExists($entry->sticker_path);
    $this->asUser($pair->second)->get(route('mood.sticker', $entry))->assertOk()->assertHeader('Content-Type', $format->getMimeType());
    $this->asUser(User::factory()->create())->get(route('mood.sticker', $entry))->assertForbidden();
    $this->asUser($pair->first)->get('/')->assertOk()->assertDontSee(route('mood.sticker', $entry), false);
    $this->asUser($pair->second)->get('/')->assertOk()->assertSee(route('mood.sticker', $entry), false);
    app()->call([$job, 'handle']);
    Http::assertSentCount(2);
})->with([MoodStickerFormat::Image, MoodStickerFormat::Animation, MoodStickerFormat::Video]);

it('rejects unsafe paths oversized downloads malformed media and external animation assets', function (string $case): void {
    $pair = AgreementScenario::create();
    $entry = MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'sticker_status' => MoodStickerStatus::Pending]);
    Http::fakeSequence()->push(['ok' => true, 'result' => ['file_path' => $case === 'path' ? '../private.tgs' : 'stickers/test.tgs', 'file_size' => $case === 'large' ? 2097153 : 32]])->push($case === 'external' ? gzencode('{"w":512,"h":512,"layers":[],"assets":[{"p":"https://example.com/private.png"}]}') : 'malformed');
    expect(fn () => app(MoodStickers::class)->download($entry->id, 'test', MoodStickerFormat::Animation))->toThrow(RuntimeException::class);
    expect(Storage::disk(MoodStickers::DISK)->allFiles())->toBe([]);
    expect($entry->refresh()->sticker_path)->toBeNull();
})->with(['path', 'large', 'malformed', 'external']);

it('discards a late sticker after a newer mood was recorded', function (): void {
    $pair = AgreementScenario::create();
    $entry = MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'sticker_status' => MoodStickerStatus::Pending]);
    MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id]);
    Http::fakeSequence()->push(['ok' => true, 'result' => ['file_path' => 'stickers/test.tgs', 'file_size' => 32]])->push(gzencode('{"w":512,"h":512,"layers":[]}'));
    app()->call([new DownloadMoodStickerJob($entry->id, 'test', MoodStickerFormat::Animation), 'handle']);
    expect($entry->refresh()->sticker_status)->toBe(MoodStickerStatus::Failed)->and($entry->sticker_path)->toBeNull();
    expect(Storage::disk(MoodStickers::DISK)->allFiles())->toBe([]);
});

it('explains an unsolicited sticker without offering an unrelated login flow', function (): void {
    Queue::fake();
    $account = TelegramAccount::factory()->create();
    $payload = ['update_id' => 456, 'message' => ['from' => ['id' => (int) $account->telegram_id, 'is_bot' => false], 'chat' => ['id' => (int) $account->telegram_id, 'type' => 'private'], 'sticker' => ['file_id' => 'test_sticker', 'is_animated' => true]]];
    $reply = app(WebhookHandler::class)->execute(IncomingUpdate::fromArray($payload), 'test-hash');
    expect($reply['text'])->toContain('Сначала ответьте на вопрос о настроении')->not->toContain('Начните вход');
    Queue::assertNotPushed(DownloadMoodStickerJob::class);
});

it('cleans an unreferenced download after a terminal failure and preserves a ready sticker', function (): void {
    $pair = AgreementScenario::create();
    $entry = MoodEntry::factory()->create(['couple_id' => $pair->couple->id, 'user_id' => $pair->first->id, 'sticker_status' => MoodStickerStatus::Pending]);
    $path = app(MoodStickers::class)->getStoragePath($entry->id, 'test', MoodStickerFormat::Animation);
    Storage::disk(MoodStickers::DISK)->put($path, 'test');
    $job = new DownloadMoodStickerJob($entry->id, 'test', MoodStickerFormat::Animation);
    $job->failed();
    expect($entry->refresh()->sticker_status)->toBe(MoodStickerStatus::Failed);
    Storage::disk(MoodStickers::DISK)->assertMissing($path);
    $entry->forceFill(['sticker_path' => $path, 'sticker_status' => MoodStickerStatus::Ready])->save();
    Storage::disk(MoodStickers::DISK)->put($path, 'ready');
    $job->failed();
    Storage::disk(MoodStickers::DISK)->assertExists($path);
    expect($entry->refresh()->sticker_status)->toBe(MoodStickerStatus::Ready);
});
