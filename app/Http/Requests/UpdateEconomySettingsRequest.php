<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\Admin\EconomySettingsData;
use App\Services\Hearts\EconomyRules;
use App\Services\Hearts\EconomySettingsStore;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateEconomySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-system-settings') === true;
    }

    /** @return array<string,list<string>> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted'], 'expected_version' => ['required', 'integer', 'min:1'], ...array_fill_keys(array_keys(app(EconomySettingsStore::class)->labels()), ['required', 'integer', sprintf('between:0,%d', EconomyRules::MAXIMUM_SETTING)])];
    }

    public function getData(): EconomySettingsData
    {
        $values = [];
        foreach (array_keys(app(EconomySettingsStore::class)->labels()) as $key) {
            $values[$key] = $this->integer($key);
        }

        return new EconomySettingsData($this->integer('expected_version'), $values);
    }
}
