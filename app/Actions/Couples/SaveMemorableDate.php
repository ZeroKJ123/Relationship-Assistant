<?php

declare(strict_types=1);

namespace App\Actions\Couples;

use App\Data\Couples\MemorableDateData;
use App\Models\Couple;
use App\Models\MemorableDate;
use App\Models\User;
use App\Services\Couples\MemorableDates;
use App\Services\Requests\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveMemorableDate
{
    public function __construct(private readonly RequestContext $context) {}

    public function execute(User $actor, Couple $couple, MemorableDateData $data): MemorableDate
    {
        Validator::make(['title' => $data->title, 'operation_key' => $data->operationKey], ['title' => ['required', 'string', 'max:120'], 'operation_key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($actor, $couple, $data): MemorableDate {
            $current = $this->context->lockCouple($actor, $couple);
            $existing = MemorableDate::query()->where('couple_id', $current->id)->where('operation_key', $data->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            if (MemorableDate::query()->where('couple_id', $current->id)->count() >= MemorableDates::MAXIMUM_PER_COUPLE) {
                throw ValidationException::withMessages(['title' => __('Можно сохранить не больше :count дат. Удалите ненужную.', ['count' => MemorableDates::MAXIMUM_PER_COUPLE])]);
            }
            $date = new MemorableDate;
            $date->forceFill(['couple_id' => $current->id, 'created_by_id' => $actor->id, 'operation_key' => $data->operationKey, 'title' => $data->title, 'occurs_on' => $data->occursOn->toDateString(), 'is_yearly' => $data->isYearly])->save();
            DB::afterCommit(fn () => Log::info('memorable_date.saved', ['date_id' => $date->id, 'actor_id' => $actor->id, 'couple_id' => $current->id]));

            return $date;
        }, attempts: 3);
    }
}
