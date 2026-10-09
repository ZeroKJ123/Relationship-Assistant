<?php

declare(strict_types=1);

namespace App\Actions\Catalogs;

use App\Data\Catalogs\SettlementMethodData;
use App\Models\SettlementMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CreateSettlementMethod
{
    private const MAXIMUM_CUSTOM_METHODS = 100;

    public function execute(User $owner, SettlementMethodData $data): SettlementMethod
    {
        $name = $data->name;
        $details = $data->details;
        $key = $data->key;
        Validator::make(['name' => $name, 'details' => $details, 'key' => $key], ['name' => ['required', 'string', 'max:100'], 'details' => ['required', 'string', 'max:500'], 'key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($owner, $name, $details, $key): SettlementMethod {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $code = sprintf('custom:%s', $key);
            $existing = SettlementMethod::query()->where('code', $code)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->user_id !== $owner->id || $existing->name !== $name || $existing->details !== $details) {
                    throw ValidationException::withMessages(['name' => __('Ключ уже использован. Обновите страницу.')]);
                }

                return $existing;
            }
            if (SettlementMethod::query()->where('user_id', $owner->id)->count() >= self::MAXIMUM_CUSTOM_METHODS) {
                throw ValidationException::withMessages(['name' => __('Достигнут предел собственных способов.')]);
            }
            $method = new SettlementMethod;
            $method->forceFill(['code' => $code, 'user_id' => $owner->id, 'kind' => 'custom', 'name' => $name, 'details' => $details, 'options' => [], 'sort_order' => 0])->save();
            DB::afterCommit(fn () => Log::info('catalogs.custom_method_created', ['method_id' => $method->id, 'user_id' => $owner->id]));

            return $method;
        }, attempts: 3);
    }
}
