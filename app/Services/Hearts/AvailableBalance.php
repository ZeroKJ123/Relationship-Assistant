<?php

declare(strict_types=1);

namespace App\Services\Hearts;

use App\Enums\ReservationStatus;
use App\Models\HeartReservation;
use App\Models\HeartWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AvailableBalance
{
    public function reserved(HeartWallet $wallet): int
    {
        $query = HeartReservation::query()->where('heart_wallet_id', $wallet->id)->where('status', ReservationStatus::Held);
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        return $query->get(['amount'])->sum('amount');
    }

    public function get(HeartWallet $wallet): int
    {
        return $wallet->balance - $this->reserved($wallet);
    }

    public function require(HeartWallet $wallet, int $amount): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Availability requires a locked wallet in a transaction.');
        }
        $free = $this->get($wallet);
        Log::debug('hearts.availability_checked', ['wallet_id' => $wallet->id, 'available' => $free, 'requested' => $amount]);
        if ($amount < 1 || $free < $amount) {
            Log::warning('hearts.insufficient_available_balance', ['wallet_id' => $wallet->id, 'available' => $free, 'requested' => $amount]);
            throw ValidationException::withMessages(['heart_price' => __('Недостаточно свободных сердечек. Принятые услуги уже занимают часть баланса.')]);
        }
    }
}
