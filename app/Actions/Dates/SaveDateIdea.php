<?php

declare(strict_types=1);

namespace App\Actions\Dates;

use App\Models\Couple;
use App\Models\DateIdea;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveDateIdea
{
    /** Больше секторов на колесе не читаются на телефоне. */
    public const MAXIMUM_PER_COUPLE = 12;

    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, string $title, string $operationKey): DateIdea
    {
        Validator::make(['title' => $title, 'operation_key' => $operationKey], ['title' => ['required', 'string', 'max:120'], 'operation_key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($actor, $couple, $title, $operationKey): DateIdea {
            $current = $this->context->lockCouple($actor, $couple);
            $existing = DateIdea::query()->where('couple_id', $current->id)->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            if (DateIdea::query()->where('couple_id', $current->id)->count() >= self::MAXIMUM_PER_COUPLE) {
                throw ValidationException::withMessages(['title' => __('На колесе помещается не больше :count идей. Удалите ненужную.', ['count' => self::MAXIMUM_PER_COUPLE])]);
            }
            $idea = new DateIdea;
            $idea->forceFill(['couple_id' => $current->id, 'created_by_id' => $actor->id, 'operation_key' => $operationKey, 'title' => $title])->save();
            DB::afterCommit(fn () => Log::info('date_idea.saved', ['idea_id' => $idea->id, 'actor_id' => $actor->id, 'couple_id' => $current->id]));

            return $idea;
        }, attempts: 3);
    }
}
