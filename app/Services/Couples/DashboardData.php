<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Enums\DutyStatus;
use App\Enums\RequestStatus;
use App\Enums\SelectionStatus;
use App\Models\Car;
use App\Models\ChoreDraw;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\MoodEntry;
use App\Models\MovieChoice;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Hearts\WalletSnapshot;
use App\Services\Mood\MoodHistory;
use App\ViewModels\DashboardViewModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

final class DashboardData
{
    private const DUTY_PREVIEW_LIMIT = 3;

    public function __construct(private readonly WalletSnapshot $wallets, private readonly MoodHistory $moods, private readonly CoupleTimeline $timeline) {}

    public function get(User $actor): DashboardViewModel
    {
        $started = hrtime(true);
        Log::debug('dashboard.load_started', ['user_id' => $actor->id]);
        $wallet = $this->wallets->get($actor);
        $couple = Couple::query()->whereRelation('members', 'active_user_id', $actor->id)->with(['members.user:id,name,avatar_path,updated_at,compliments', 'agreement'])->first();
        $partner = $couple?->members->first(fn ($member): bool => $member->active_user_id !== null && $member->user_id !== $actor->id)?->user;
        $compliments = $partner === null ? [] : ($partner->compliments ?? []);
        $compliment = $compliments === [] ? __('Забота начинается с маленьких вещей') : $compliments[intdiv(time(), 300) % count($compliments)];
        $days = $couple === null ? null : $this->timeline->getDaysTogether($couple);
        $duties = $couple === null ? new Collection : Duty::query()->where('couple_id', $couple->id)->where('executor_id', $actor->id)->whereIn('status', [DutyStatus::Pending, DutyStatus::Reported, DutyStatus::Disputed, DutyStatus::Overdue])->orderBy('due_at')->orderBy('id')->limit(self::DUTY_PREVIEW_LIMIT)->get();
        $incoming = $couple === null ? null : PartnerRequest::query()->where('couple_id', $couple->id)->where('due_at', '>', now())->where(function (Builder $query) use ($actor): void {
            $query->where(fn (Builder $incoming) => $incoming->where('recipient_id', $actor->id)->where('status', RequestStatus::Pending))
                ->orWhere(fn (Builder $conditional) => $conditional->where('author_id', $actor->id)->where('status', RequestStatus::Conditional));
        })->with(['author:id,name', 'recipient:id,name'])->latest('id')->first();
        Log::debug('dashboard.loaded', ['user_id' => $actor->id, 'couple_id' => $couple?->id, 'duty_count' => $duties->count(), 'elapsed_ms' => (hrtime(true) - $started) / 1_000_000]);

        return new DashboardViewModel($actor, $couple, $partner, $days, $wallet, $couple === null ? new Collection : $this->moods->getLatest($couple)->filter(fn (MoodEntry $entry): bool => $entry->user_id !== $actor->id)->values(), $duties, $incoming, $couple !== null && $couple->agreement?->active_version === null, $couple === null ? null : $this->timeline->getDuration($couple), $couple === null ? null : $this->moods->getPendingPrompt($actor, $couple), Car::query()->where('user_id', $actor->id)->exists(), $compliment, $couple === null ? null : MovieChoice::query()->where('couple_id', $couple->id)->whereIn('status', [SelectionStatus::Waiting, SelectionStatus::Active])->latest('id')->first(), $couple === null ? null : ChoreDraw::query()->where('couple_id', $couple->id)->where('status', SelectionStatus::Waiting)->latest('id')->first());
    }
}
