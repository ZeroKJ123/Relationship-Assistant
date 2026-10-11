<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Models\Couple;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class UpdateTimezone
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, string $timezone, string $expectedTimezone): void
    {
        DB::transaction(function () use ($actor, $couple, $timezone, $expectedTimezone): void {
            $current = $this->context->lockCouple($actor, $couple);
            if ($current->timezone !== $expectedTimezone) {
                throw ValidationException::withMessages(['timezone' => __('Часовой пояс уже изменён. Обновите настройки.')]);
            }
            $current->forceFill(['timezone' => $timezone])->save();
            DB::afterCommit(fn () => Log::info('couple.timezone_updated', ['couple_id' => $couple->id, 'actor_id' => $actor->id]));
        }, attempts: 3);
    }
}
