<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Models\Couple;
use App\Models\StreakRestore;
use App\Models\User;
use App\Services\Couples\CoupleStreak;
use App\Services\Requests\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class RestoreStreak
{
    public function __construct(private readonly RequestContext $context, private readonly CoupleStreak $streak) {}

    public function execute(User $actor, Couple $couple): void
    {
        DB::transaction(function () use ($actor, $couple): void {
            $current = $this->context->lockCouple($actor, $couple);
            $state = $this->streak->get($current, $actor, CarbonImmutable::now());
            if (! $state->canRestore()) {
                throw ValidationException::withMessages(['streak' => __('Серию сейчас нельзя восстановить: пропусков нет или не хватает восстановлений в этом месяце.')]);
            }
            foreach ($state->missedDays as $day) {
                $restore = new StreakRestore;
                $restore->forceFill(['couple_id' => $current->id, 'user_id' => $actor->id, 'restored_on' => $day])->save();
            }
            DB::afterCommit(fn () => Log::info('streak.restored', ['couple_id' => $current->id, 'actor_id' => $actor->id, 'days' => count($state->missedDays)]));
        }, attempts: 3);
    }
}
