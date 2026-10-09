<?php

declare(strict_types=1);

namespace App\Services\Hearts;

use App\Models\EconomySetting;
use App\Models\EconomySettingsVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final class EconomySettingsStore
{
    /** @return array<string,string> */
    public function labels(): array
    {
        return ['starting_bonus' => 'Стартовые сердечки', 'positive_cap' => 'Потолок баланса', 'reward' => 'Награда за дело', 'reward_daily' => 'Награды за день', 'reward_weekly' => 'Награды за неделю', 'manual_thanks_daily' => 'Благодарности за день', 'manual_thanks_weekly' => 'Благодарности за неделю', 'manual_deduction_daily' => 'Списания за день', 'manual_deduction_weekly' => 'Списания за неделю', 'refusal_tariff' => 'Штраф отказа «Очень хочу»', 'overdue_penalty' => 'Штраф просрочки', 'false_report_penalty' => 'Штраф ложной отметки'];
    }

    /** @return array<string,int> */
    public function read(): array
    {
        $query = EconomySetting::query()->whereKey(EconomySetting::SINGLETON_ID);
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $state = $query->firstOrFail();
        if ($state->current_version_id === null) {
            $version = $this->initialize();

            return ['version' => $version->version, ...$version->settings];
        }
        $versionQuery = EconomySettingsVersion::query()->whereKey($state->current_version_id);
        if (DB::transactionLevel() > 0) {
            $versionQuery->lockForUpdate();
        }
        $version = $versionQuery->firstOrFail();

        return ['version' => $version->version, ...$version->settings];
    }

    public function initialize(): EconomySettingsVersion
    {
        return DB::transaction(function (): EconomySettingsVersion {
            $state = EconomySetting::query()->whereKey(EconomySetting::SINGLETON_ID)->lockForUpdate()->firstOrFail();
            if ($state->current_version_id !== null) {
                return EconomySettingsVersion::query()->whereKey($state->current_version_id)->lockForUpdate()->firstOrFail();
            }
            $values = $this->bootstrapValues();
            unset($values['version']);
            $this->validate($values);
            $version = new EconomySettingsVersion;
            $version->forceFill(['version' => 1, 'settings' => $values, 'created_by_id' => null])->save();
            $state->forceFill(['current_version_id' => $version->id])->save();
            DB::afterCommit(fn () => Log::info('economy.settings_initialized', ['version' => $version->version]));

            return $version;
        }, attempts: 3);
    }

    /** @param array<string,int> $values */
    public function validate(array $values): void
    {
        $rules = array_fill_keys(array_keys($this->labels()), ['required', 'integer', sprintf('between:0,%d', EconomyRules::MAXIMUM_SETTING)]);
        Validator::make($values, $rules)->validate();
        if (array_diff(array_keys($values), array_keys($rules)) !== []) {
            throw ValidationException::withMessages(['settings' => __('Переданы неизвестные настройки.')]);
        }
        if ($values['positive_cap'] < 1 || $values['starting_bonus'] > $values['positive_cap'] || $values['reward'] > $values['reward_daily'] || $values['reward_daily'] > $values['reward_weekly'] || $values['manual_thanks_daily'] > $values['manual_thanks_weekly'] || $values['manual_deduction_daily'] > $values['manual_deduction_weekly']) {
            throw ValidationException::withMessages(['settings' => __('Потолок должен быть положительным и не меньше старта; дневные пределы не должны превышать недельные, награда — дневной предел.')]);
        }
    }

    /** @return array<string,int> */
    private function bootstrapValues(): array
    {
        $version = config('economy.version');
        if (! is_int($version) || $version < 1) {
            throw new LogicException('Economy version must be positive.');
        }
        $values = ['version' => $version];
        foreach (array_keys($this->labels()) as $key) {
            $value = config(sprintf('economy.%s', $key));
            if (! is_int($value)) {
                throw new LogicException('Economy defaults must be integers.');
            }
            $values[$key] = $value;
        }

        return $values;
    }
}
