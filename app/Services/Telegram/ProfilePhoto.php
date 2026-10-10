<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\Media\PrivatePhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class ProfilePhoto
{
    private const MAXIMUM_BYTES = 1048576;

    public function __construct(private readonly BotClient $bot, private readonly PrivatePhotos $photos) {}

    public function import(User $user, TelegramAccount $account): void
    {
        if ($user->avatar_path !== null || ! $this->bot->isEnabled()) {
            return;
        }
        $temporary = tempnam(sys_get_temp_dir(), 'ha-avatar-');
        if ($temporary === false) {
            Log::warning('telegram.avatar_skipped', ['user_id' => $user->id, 'reason' => 'temporary_file_unavailable']);

            return;
        }
        try {
            $path = $this->download($account->telegram_id, $temporary);
            if ($path === null) {
                return;
            }
            $stored = $this->photos->store(new UploadedFile($temporary, 'avatar.jpg', test: true));
            if (User::query()->whereKey($user->id)->whereNull('avatar_path')->update(['avatar_path' => $stored]) !== 1) {
                $this->photos->discard($stored);
            }
            Log::debug('telegram.avatar_imported', ['user_id' => $user->id]);
        } catch (Throwable $exception) {
            Log::warning('telegram.avatar_skipped', ['user_id' => $user->id, 'reason' => 'photo_unavailable', 'exception_class' => $exception::class]);
        } finally {
            unlink($temporary);
        }
    }

    private function download(string $telegramId, string $temporary): ?string
    {
        $photos = $this->bot->request('getUserProfilePhotos', ['user_id' => $telegramId, 'limit' => 1]);
        $fileId = data_get($photos, 'photos.0.0.file_id');
        if (! is_string($fileId)) {
            return null;
        }
        $file = $this->bot->request('getFile', ['file_id' => $fileId]);
        $filePath = $file['file_path'] ?? null;
        $fileSize = $file['file_size'] ?? null;
        if (! is_string($filePath) || preg_match('/^photos\/[A-Za-z0-9_-]+\.[A-Za-z0-9]+$/D', $filePath) !== 1 || ! is_int($fileSize) || $fileSize < 1 || $fileSize > self::MAXIMUM_BYTES) {
            return null;
        }
        $response = Http::connectTimeout(3)->timeout(10)->withoutRedirecting()->withOptions(['sink' => $temporary, 'progress' => function (int|float $total, int|float $downloaded): void {
            if ($total > self::MAXIMUM_BYTES || $downloaded > self::MAXIMUM_BYTES) {
                throw new RuntimeException('Profile photo exceeds size limit.');
            }
        }])->get(sprintf('https://api.telegram.org/file/bot%s/%s', config()->string('services.telegram.bot_token'), $filePath));
        if (! $response->successful() || filesize($temporary) > self::MAXIMUM_BYTES) {
            return null;
        }

        return $temporary;
    }
}
