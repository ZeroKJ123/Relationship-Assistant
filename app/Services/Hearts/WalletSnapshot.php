<?php

declare(strict_types=1);

namespace App\Services\Hearts;

use App\Data\Hearts\BalanceSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class WalletSnapshot
{
    public function __construct(private readonly Ledger $ledger, private readonly AvailableBalance $available) {}

    public function get(User $actor): BalanceSnapshot
    {
        return DB::transaction(function () use ($actor): BalanceSnapshot {
            $wallet = $this->ledger->lockWallet($actor);
            $reserved = $this->available->reserved($wallet);

            return new BalanceSnapshot($wallet->balance, $reserved, $wallet->balance - $reserved);
        }, attempts: 3);
    }
}
