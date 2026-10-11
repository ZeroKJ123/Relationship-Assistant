<?php

declare(strict_types=1);

namespace App\Enums;

enum MoodStickerFormat: string
{
    case Image = 'webp';
    case Animation = 'json';
    case Video = 'webm';

    public function getMimeType(): string
    {
        return match ($this) {
            self::Image => 'image/webp',
            self::Animation => 'application/json',
            self::Video => 'video/webm',
        };
    }
}
