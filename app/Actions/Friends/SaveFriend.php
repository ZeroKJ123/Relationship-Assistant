<?php

declare(strict_types=1);

namespace App\Actions\Friends;

use App\Data\Friends\FriendData;
use App\Models\Friend;
use App\Models\User;
use App\Services\Catalogs\CatalogVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveFriend
{
    public function __construct(private readonly CatalogVersions $versions) {}

    public function execute(User $actor, FriendData $data, ?Friend $friend = null): Friend
    {
        if ($friend !== null) {
            Gate::forUser($actor)->authorize('update', $friend);
        }
        Validator::make(['name' => $data->name, 'operation_key' => $data->operationKey, 'expected_version' => $data->expectedVersion], ['name' => ['required', 'string', 'max:120'], 'operation_key' => ['required', 'uuid'], 'expected_version' => ['integer', 'min:0']])->validate();
        if ($friend === null && $data->expectedVersion !== 0) {
            throw ValidationException::withMessages(['expected_version' => __('Обновите форму создания друга.')]);
        }

        return DB::transaction(function () use ($actor, $data, $friend): Friend {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current = $friend === null ? Friend::query()->where('user_id', $actor->id)->where('operation_key', $data->operationKey)->lockForUpdate()->first() : Friend::query()->whereKey($friend->id)->lockForUpdate()->firstOrFail();
            if ($current !== null) {
                Gate::forUser($actor)->authorize('update', $current);
                if ($this->versions->isRepeated($current, $data->operationKey, $friend === null, ['name' => $data->name], null)) {
                    return $current;
                }
                $this->versions->requireChange($current, $data->expectedVersion, $friend === null);
            }
            if ($current === null) {
                $current = new Friend;
                $current->operation_key = $data->operationKey;
            }
            $current->forceFill(['user_id' => $actor->id, 'name' => $data->name, 'last_operation_key' => $data->operationKey, 'version' => $friend === null ? 0 : $current->version + 1])->save();
            DB::afterCommit(fn () => Log::info('friend.saved', ['friend_id' => $current->id, 'actor_id' => $actor->id, 'version' => $current->version]));

            return $current;
        }, attempts: 3);
    }
}
