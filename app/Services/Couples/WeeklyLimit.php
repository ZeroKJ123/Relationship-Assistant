<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Enums\CoupleStatus;
use App\Models\Couple;
use Illuminate\Validation\ValidationException;

final class WeeklyLimit
{
    // Accepted D06 bounds; separate from the future heart reward tariffs.
    public const MINIMUM = 1;

    public const MAXIMUM = 14;

    public function validate(int $value): void
    {
        if ($value < self::MINIMUM || $value > self::MAXIMUM) {
            throw ValidationException::withMessages(['weekly_limit' => __('Выберите число от :min до :max.', ['min' => self::MINIMUM, 'max' => self::MAXIMUM])]);
        }
    }

    public function suggestCompromise(int $first, int $second): int
    {
        $this->validate($first);
        $this->validate($second);

        // Integer mean rounded upward, with no floating-point conversion.
        return intdiv($first + $second + 1, 2);
    }

    public function getActiveLimit(Couple $couple): ?int
    {
        if ($couple->status !== CoupleStatus::Active) {
            return null;
        }

        return $couple->agreement()->first()?->active_weekly_limit;
    }
}
