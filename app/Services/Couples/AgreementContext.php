<?php

declare(strict_types=1);

namespace App\Services\Couples;

use App\Enums\CoupleStatus;
use App\Models\Couple;
use App\Models\CoupleAgreement;
use App\Models\CoupleMember;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AgreementContext
{
    public function __construct(private readonly CoupleLock $lock) {}

    public function lockForUpdate(User $actor, Couple $couple): CoupleAgreement
    {
        Gate::forUser($actor)->authorize('update', $couple);
        $memberIds = $this->getMemberIds($couple);
        $this->lock->lockUsers($memberIds);
        $current = $this->lock->lockCouple($couple->id);
        Gate::forUser($actor)->authorize('update', $current);
        if ($current->status !== CoupleStatus::Active || $this->getMemberIds($current, isLocked: true) !== $memberIds) {
            throw ValidationException::withMessages(['agreement' => __('Согласование доступно только действующей паре. Обновите страницу.')]);
        }
        $agreement = $current->agreement()->lockForUpdate()->first();
        if ($agreement !== null) {
            return $agreement;
        }
        $agreement = new CoupleAgreement;
        $agreement->forceFill(['couple_id' => $current->id, 'current_version' => 0])->save();

        return $agreement;
    }

    public function requireVersion(CoupleAgreement $agreement, int $version): void
    {
        if ($agreement->current_version !== $version) {
            Log::warning('agreement.stale_version', ['agreement_id' => $agreement->id, 'expected_version' => $version, 'current_version' => $agreement->current_version]);
            throw ValidationException::withMessages(['agreement' => __('Вариант изменился. Обновите страницу и рассмотрите новую версию.')]);
        }
    }

    /** @return list<int> */
    private function getMemberIds(Couple $couple, bool $isLocked = false): array
    {
        $query = $couple->members()->orderBy('user_id');
        if ($isLocked) {
            $query->lockForUpdate();
        }

        return array_values($query->get()->map(fn (CoupleMember $member): int => $member->user_id)->all());
    }
}
