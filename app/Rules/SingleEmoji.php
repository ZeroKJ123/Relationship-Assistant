<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SingleEmoji implements ValidationRule
{
    private const MAXIMUM_CODEPOINTS = 32;

    private const EMOJI_SEQUENCE = '/^(?:\p{Extended_Pictographic}\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?(?:\x{200D}\p{Extended_Pictographic}\x{FE0F}?[\x{1F3FB}-\x{1F3FF}]?)*(?:[\x{E0020}-\x{E007E}]+\x{E007F})?|[\x{1F1E6}-\x{1F1FF}]{2}|[0-9#*]\x{FE0F}?\x{20E3})$/uD';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) > self::MAXIMUM_CODEPOINTS || preg_match(self::EMOJI_SEQUENCE, $value) !== 1) {
            $fail(__('Напишите один эмоджи, который описывает ваше настроение.'));
        }
    }
}
