<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Admin\EconomySettingsData;
use App\Models\EconomySetting;
use App\Models\EconomySettingsVersion;
use App\Models\User;
use App\Services\Hearts\EconomySettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class UpdateEconomySettings
{
    public function __construct(private readonly EconomySettingsStore $store) {}

    public function execute(User $actor, EconomySettingsData $data): EconomySettingsVersion
    {
        Gate::forUser($actor)->authorize('manage-system-settings');
        $this->store->validate($data->settings);

        return DB::transaction(function () use ($actor, $data): EconomySettingsVersion {
            $currentActor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($currentActor)->authorize('manage-system-settings');
            $current = $this->store->initialize();
            if ($current->version !== $data->expectedVersion) {
                if ($current->version === $data->expectedVersion + 1 && $current->created_by_id === $actor->id && $current->settings === $data->settings) {
                    return $current;
                }
                Log::warning('economy.settings_conflict', ['actor_id' => $actor->id, 'version' => $current->version]);
                throw ValidationException::withMessages(['settings' => __('Настройки изменились. Обновите страницу перед подтверждением.')]);
            }
            if ($current->settings === $data->settings) {
                return $current;
            }
            $next = new EconomySettingsVersion;
            $next->forceFill(['version' => $current->version + 1, 'settings' => $data->settings, 'created_by_id' => $actor->id])->save();
            EconomySetting::query()->whereKey(EconomySetting::SINGLETON_ID)->update(['current_version_id' => $next->id]);
            DB::afterCommit(fn () => Log::info('economy.settings_updated', ['actor_id' => $actor->id, 'version' => $next->version, 'settings' => $next->settings]));

            return $next;
        }, attempts: 3);
    }
}
