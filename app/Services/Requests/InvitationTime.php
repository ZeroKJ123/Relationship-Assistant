<?php

declare(strict_types=1);

namespace App\Services\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class InvitationTime
{
    public function parse(string $value, string $timezone, string $field = 'starts_at'): CarbonImmutable
    {
        $time = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
        if ($time === null || $time->format('Y-m-d\TH:i') !== $value) {
            Log::warning('invitation.time_denied', ['reason' => 'invalid_local_time']);
            throw ValidationException::withMessages([$field => __('Такого времени в часовом поясе пары нет. Выберите другое.')]);
        }
        Log::debug('invitation.time_normalized', ['timezone' => $timezone]);

        return $time->utc();
    }
}
