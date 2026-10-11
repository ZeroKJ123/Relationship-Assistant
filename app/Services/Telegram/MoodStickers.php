<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Enums\MoodStickerFormat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class MoodStickers
{
    public const DISK = 'mood_stickers';

    private const MAXIMUM_BYTES = 2097152;

    private const MAXIMUM_JSON_BYTES = 4194304;

    public function __construct(private readonly BotClient $bot) {}

    public function download(int $entryId, string $fileId, MoodStickerFormat $format): string
    {
        $file = $this->bot->request('getFile', ['file_id' => $fileId]);
        $remotePath = $file['file_path'] ?? null;
        $size = $file['file_size'] ?? null;
        $extension = $format === MoodStickerFormat::Animation ? 'tgs' : $format->value;
        if (! is_string($remotePath) || preg_match(sprintf('/^(?:stickers|videos|documents)\/[A-Za-z0-9_-]+\.%s$/D', $extension), $remotePath) !== 1 || ! is_int($size) || $size < 1 || $size > self::MAXIMUM_BYTES) {
            throw new RuntimeException('Sticker unavailable or exceeds limit.');
        }
        $temporary = tempnam(sys_get_temp_dir(), 'ha-sticker-');
        if ($temporary === false) {
            throw new RuntimeException('Sticker temporary file unavailable.');
        }
        try {
            $response = Http::connectTimeout(3)->timeout(10)->withoutRedirecting()->withOptions([
                'sink' => $temporary,
                'progress' => function (int|float $total, int|float $downloaded): void {
                    if ($total > self::MAXIMUM_BYTES || $downloaded > self::MAXIMUM_BYTES) {
                        throw new RuntimeException('Sticker exceeds limit.');
                    }
                },
            ])->get(sprintf('https://api.telegram.org/file/bot%s/%s', config()->string('services.telegram.bot_token'), $remotePath));
            $bytes = file_get_contents($temporary);
            if (! $response->successful() || $bytes === false || strlen($bytes) > self::MAXIMUM_BYTES || $bytes === '') {
                throw new RuntimeException('Sticker download failed.');
            }
            $bytes = $this->validate($bytes, $format);
            $path = $this->getStoragePath($entryId, $fileId, $format);
            Storage::disk(self::DISK)->put($path, $bytes);

            return $path;
        } catch (Throwable) {
            throw new RuntimeException('Sticker download or validation failed.');
        } finally {
            unlink($temporary);
        }
    }

    public function getStoragePath(int $entryId, string $fileId, MoodStickerFormat $format): string
    {
        return sprintf('%d-%s.%s', $entryId, hash('sha256', $fileId), $format->value);
    }

    private function validate(string $bytes, MoodStickerFormat $format): string
    {
        if ($format === MoodStickerFormat::Image) {
            $info = getimagesizefromstring($bytes);
            if ($info === false || $info['mime'] !== 'image/webp' || $info[0] > 512 || $info[1] > 512) {
                throw new RuntimeException('Invalid sticker image.');
            }

            return $bytes;
        }
        if ($format === MoodStickerFormat::Video) {
            if (! str_starts_with($bytes, "\x1A\x45\xDF\xA3") || ! str_contains(substr($bytes, 0, 4096), 'webm')) {
                throw new RuntimeException('Invalid sticker video.');
            }

            return $bytes;
        }
        $json = gzdecode($bytes, self::MAXIMUM_JSON_BYTES);
        if ($json === false || strlen($json) >= self::MAXIMUM_JSON_BYTES) {
            throw new RuntimeException('Invalid sticker animation.');
        }
        $animation = json_decode($json, associative: true, depth: 64, flags: JSON_THROW_ON_ERROR);
        if (! is_array($animation) || ! is_array($animation['layers'] ?? null) || ! is_numeric($animation['w'] ?? null) || ! is_numeric($animation['h'] ?? null) || $animation['w'] < 1 || $animation['h'] < 1 || $animation['w'] > 512 || $animation['h'] > 512) {
            throw new RuntimeException('Invalid sticker animation dimensions.');
        }
        $this->rejectExternalAssets($animation);

        return $json;
    }

    /** @param array<array-key,mixed> $node */
    private function rejectExternalAssets(array $node): void
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $this->rejectExternalAssets($value);
            }
            if (is_string($value) && in_array($key, ['p', 'u', 'x'], true)) {
                throw new RuntimeException('External assets and expressions are not allowed.');
            }
        }
    }
}
