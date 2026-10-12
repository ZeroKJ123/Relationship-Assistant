<?php

declare(strict_types=1);

namespace App\Actions\Rewards;

use App\Data\Rewards\RewardData;
use App\Models\Reward;
use App\Models\User;
use App\Services\Catalogs\CatalogVersions;
use App\Services\Hearts\EconomyRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveReward
{
    public function __construct(private readonly CatalogVersions $versions) {}

    public function execute(User $actor, RewardData $data, ?Reward $reward = null): Reward
    {
        if ($reward !== null) {
            Gate::forUser($actor)->authorize('update', $reward);
        }
        Validator::make(
            ['title' => $data->title, 'description' => $data->description, 'heart_price' => $data->heartPrice, 'operation_key' => $data->operationKey, 'expected_version' => $data->expectedVersion],
            ['title' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500'], 'heart_price' => ['required', 'integer', sprintf('between:1,%d', EconomyRules::MAXIMUM_SETTING)], 'operation_key' => ['required', 'uuid'], 'expected_version' => ['integer', 'min:0']],
        )->validate();
        if ($reward === null && $data->expectedVersion !== 0) {
            throw ValidationException::withMessages(['expected_version' => __('Обновите форму создания награды.')]);
        }

        return DB::transaction(function () use ($actor, $data, $reward): Reward {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current = $reward === null ? Reward::query()->where('user_id', $actor->id)->where('operation_key', $data->operationKey)->lockForUpdate()->first() : Reward::query()->whereKey($reward->id)->lockForUpdate()->firstOrFail();
            $payload = ['title' => $data->title, 'description' => $data->description, 'heart_price' => $data->heartPrice];
            if ($current !== null) {
                Gate::forUser($actor)->authorize('update', $current);
                if ($this->versions->isRepeated($current, $data->operationKey, $reward === null, $payload, null)) {
                    return $current;
                }
                $this->versions->requireChange($current, $data->expectedVersion, $reward === null);
            }
            if ($current === null) {
                $current = new Reward;
                $current->operation_key = $data->operationKey;
            }
            $current->forceFill([...$payload, 'user_id' => $actor->id, 'last_operation_key' => $data->operationKey, 'version' => $reward === null ? 0 : $current->version + 1])->save();
            DB::afterCommit(fn () => Log::info('reward.saved', ['reward_id' => $current->id, 'actor_id' => $actor->id, 'version' => $current->version]));

            return $current;
        }, attempts: 3);
    }
}
