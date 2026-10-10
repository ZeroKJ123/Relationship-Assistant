<?php

declare(strict_types=1);

namespace App\Services\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class DecimalAmount
{
    public const MAXIMUM = '1000000.00';

    public function normalize(string $value, string $field = 'amount', bool $isPositive = false): string
    {
        if (preg_match('/\A(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,2})?\z/D', $value) !== 1) {
            Log::debug('money.validation_denied', ['reason' => 'invalid_decimal_format']);
            throw ValidationException::withMessages([$field => __('Введите сумму без округления, не более двух цифр после точки.')]);
        }
        $amount = BigDecimal::of($value)->toScale(2, RoundingMode::Unnecessary);
        if ($amount->isGreaterThan(self::MAXIMUM) || ($isPositive && $amount->isLessThanOrEqualTo('0'))) {
            Log::debug('money.validation_denied', ['reason' => 'amount_out_of_range']);
            throw ValidationException::withMessages([$field => __('Сумма должна быть в допустимых пределах, для запроса денег — больше нуля.')]);
        }

        return (string) $amount;
    }
}
