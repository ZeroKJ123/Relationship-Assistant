<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Enums\DutyStatus;
use App\Enums\RequestStatus;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\WalletSnapshot;
use App\Services\Mood\MoodHistory;
use App\ViewModels\DashboardViewModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

final class DashboardData
{
    private const DUTY_PREVIEW_LIMIT = 3;

    public function __construct(private readonly WalletSnapshot $wallets, private readonly MoodHistory $moods) {}

    public function get(User $actor): DashboardViewModel
    {
        $started = hrtime(true);
        Log::debug('dashboard.load_started', ['user_id' => $actor->id]);
        $wallet = $this->wallets->get($actor);
        $couple = Couple::query()->whereRelation('members', 'active_user_id', $actor->id)->with(['members.user:id,name', 'agreement'])->first();
        $partner = $couple?->members->first(fn ($member): bool => $member->active_user_id !== null && $member->user_id !== $actor->id)?->user;
        $days = $couple === null ? null : max(0, (int) CarbonImmutable::parse($couple->started_on->format('Y-m-d'), $couple->timezone)->diffInDays(CarbonImmutable::now($couple->timezone)->startOfDay()));
        $duties = $couple === null ? new Collection : Duty::query()->where('couple_id', $couple->id)->where('executor_id', $actor->id)->whereIn('status', [DutyStatus::Pending, DutyStatus::Reported, DutyStatus::Disputed, DutyStatus::Overdue])->orderBy('due_at')->orderBy('id')->limit(self::DUTY_PREVIEW_LIMIT)->get();
        $incoming = $couple === null ? null : PartnerRequest::query()->where('couple_id', $couple->id)->where('recipient_id', $actor->id)->where('status', RequestStatus::Pending)->with('author:id,name')->latest('id')->first();
        Log::debug('dashboard.loaded', ['user_id' => $actor->id, 'couple_id' => $couple?->id, 'duty_count' => $duties->count(), 'elapsed_ms' => (hrtime(true) - $started) / 1_000_000]);

        return new DashboardViewModel($actor, $couple, $partner, $days, $wallet, $couple === null ? new Collection : $this->moods->getLatest($couple), $duties, $incoming, $couple !== null && $couple->agreement?->active_version === null);
    }
}
