<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HeartWallet;
use App\Models\User;
use App\Services\Hearts\Ledger;
use Illuminate\Console\Command;

final class CheckHeartBalances extends Command
{
    protected $signature = 'hearts:check-balances';

    protected $description = 'Verify personal heart balances against their immutable history';

    public function handle(Ledger $ledger): int
    {
        foreach (User::query()->whereIn('id', HeartWallet::query()->select('user_id'))->lazyById() as $user) {
            $ledger->reconcile($user);
        }
        $this->info('Heart balances match their history.');

        return self::SUCCESS;
    }
}
