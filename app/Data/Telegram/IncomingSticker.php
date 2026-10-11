<?php

declare(strict_types=1);

namespace App\Data\Telegram;

use App\Enums\MoodStickerFormat;

final readonly class IncomingSticker
{
    public function __construct(public string $fileId, public MoodStickerFormat $format) {}

    /** @param array<string, mixed> $sticker */
    public static function fromArray(array $sticker): ?self
    {
        $fileId = $sticker['file_id'] ?? null;
        if (! is_string($fileId) || preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $fileId) !== 1) {
            return null;
        }
        $format = ($sticker['is_animated'] ?? false) === true ? MoodStickerFormat::Animation : (($sticker['is_video'] ?? false) === true ? MoodStickerFormat::Video : MoodStickerFormat::Image);

        return new self($fileId, $format);
    }
}
