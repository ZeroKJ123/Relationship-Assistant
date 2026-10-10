<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SingleEmoji implements ValidationRule
{
    private const MAXIMUM_CODEPOINTS = 32;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isSingleGrapheme = is_string($value) && mb_strlen($value) <= self::MAXIMUM_CODEPOINTS && preg_match('/^\X$/uD', $value) === 1;
        $hasEmoji = is_string($value) && preg_match('/\p{Extended_Pictographic}|[\x{1F1E6}-\x{1F1FF}]{2}|[0-9#*]\x{FE0F}?\x{20E3}/u', $value) === 1;
        $hasOnlyEmojiParts = is_string($value) && preg_match('/^[\p{Extended_Pictographic}\x{1F1E6}-\x{1F1FF}\x{1F3FB}-\x{1F3FF}\x{200D}\x{FE0F}\x{20E3}\x{E0020}-\x{E007F}0-9#*]+$/uD', $value) === 1;
        if (! $isSingleGrapheme || ! $hasEmoji || ! $hasOnlyEmojiParts) {
            $fail(__('Напишите один эмоджи, который описывает ваше настроение.'));
        }
    }
}
