<?php

declare(strict_types=1);

namespace App\Actions\Dates;

use App\Models\Couple;
use App\Models\DateIdea;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeleteDateIdea
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, DateIdea $idea): void
    {
        DB::transaction(function () use ($actor, $couple, $idea): void {
            $current = $this->context->lockCouple($actor, $couple);
            $stored = DateIdea::query()->whereKey($idea->id)->where('couple_id', $current->id)->lockForUpdate()->first();
            if ($stored === null) {
                return;
            }
            $stored->delete();
            DB::afterCommit(fn () => Log::info('date_idea.deleted', ['idea_id' => $idea->id, 'actor_id' => $actor->id, 'couple_id' => $current->id]));
        }, attempts: 3);
    }
}
