<?php

declare(strict_types=1);

namespace App\Actions\Catalogs;

use App\Data\Catalogs\CarData;
use App\Models\Car;
use App\Models\SettlementMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateCar
{
    public const FUELS = ['petrol' => 'Бензин', 'diesel' => 'Дизель', 'gas' => 'Газ', 'electric' => 'Зарядка'];

    public function execute(User $actor, CarData $data): Car
    {
        $name = trim($data->name);
        $fuel = $data->fuel;
        $operationKey = $data->operationKey;
        Validator::make(['name' => $name, 'fuel' => $fuel, 'operation_key' => $operationKey], ['name' => ['required', 'string', 'max:60'], 'fuel' => ['required', Rule::in(array_keys(self::FUELS))], 'operation_key' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($actor, $name, $fuel, $operationKey): Car {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existing = Car::query()->where('user_id', $actor->id)->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->name !== $name || $existing->fuel !== $fuel) {
                    throw ValidationException::withMessages(['name' => __('Эта отправка уже сохранена с другими данными.')]);
                }

                return $existing;
            }
            $method = new SettlementMethod;
            $method->forceFill(['user_id' => $actor->id, 'code' => sprintf('car:%s', $operationKey), 'kind' => 'fuel', 'name' => sprintf('%s: %s', __(self::FUELS[$fuel]), $name), 'details' => sprintf('%s — %s. %s', $name, __(self::FUELS[$fuel]), __('Объём или бюджет заправки укажите в описании условия.')), 'options' => [], 'sort_order' => 0])->save();
            $car = new Car;
            $car->forceFill(['user_id' => $actor->id, 'name' => $name, 'fuel' => $fuel, 'operation_key' => $operationKey, 'settlement_method_id' => $method->id])->save();
            DB::afterCommit(fn () => Log::info('car.created', ['car_id' => $car->id, 'actor_id' => $actor->id]));

            return $car;
        }, attempts: 3);
    }
}
