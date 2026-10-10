<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Data\Couples\RelationshipDatesData;
use App\Models\Couple;
use App\Models\User;
use App\Services\Requests\RequestContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

final class UpdateRelationshipDates
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, RelationshipDatesData $data): void
    {
        $startedOn = $data->startedOn;
        $marriedOn = $data->marriedOn;
        $version = $data->expectedVersion;
        $today = CarbonImmutable::now($couple->timezone)->toDateString();
        Validator::make(['started_on' => $startedOn, 'married_on' => $marriedOn], ['started_on' => ['required', 'date_format:Y-m-d', sprintf('before_or_equal:%s', $today)], 'married_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:started_on', sprintf('before_or_equal:%s', $today)]])->validate();
        DB::transaction(function () use ($actor, $couple, $startedOn, $marriedOn, $version): void {
            $current = $this->context->lockCouple($actor, $couple);
            if ($current->dates_version === $version + 1 && $current->started_on->toDateString() === $startedOn && $current->married_on?->toDateString() === $marriedOn) {
                return;
            }
            if ($current->dates_version !== $version) {
                $this->context->deny('relationship_dates_changed', coupleId: $current->id);
            }
            $current->forceFill(['started_on' => $startedOn, 'married_on' => $marriedOn, 'dates_version' => $current->dates_version + 1])->save();
            DB::afterCommit(fn () => Log::info('couple.dates_updated', ['couple_id' => $current->id, 'actor_id' => $actor->id, 'version' => $current->dates_version]));
        }, attempts: 3);
    }
}
