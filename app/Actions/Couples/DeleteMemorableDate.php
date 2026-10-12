<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Models\Couple;
use App\Models\MemorableDate;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeleteMemorableDate
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, MemorableDate $date): void
    {
        DB::transaction(function () use ($actor, $couple, $date): void {
            $current = $this->context->lockCouple($actor, $couple);
            $stored = MemorableDate::query()->whereKey($date->id)->where('couple_id', $current->id)->lockForUpdate()->first();
            if ($stored === null) {
                return;
            }
            $stored->delete();
            DB::afterCommit(fn () => Log::info('memorable_date.deleted', ['date_id' => $date->id, 'actor_id' => $actor->id, 'couple_id' => $current->id]));
        }, attempts: 3);
    }
}
