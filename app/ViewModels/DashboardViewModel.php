<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Data\Hearts\BalanceSnapshot;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\MoodEntry;
use App\Models\PartnerRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class DashboardViewModel
{
    /**
     * @param  Collection<int,MoodEntry>  $latestMoods
     * @param  Collection<int,Duty>  $duties
     */
    public function __construct(
        public User $actor,
        public ?Couple $couple,
        public ?User $partner,
        public ?int $togetherDays,
        public BalanceSnapshot $wallet,
        public Collection $latestMoods,
        public Collection $duties,
        public ?PartnerRequest $newRequest,
        public bool $isAgreementPending,
        public ?string $togetherDuration = null,
    ) {}
}
