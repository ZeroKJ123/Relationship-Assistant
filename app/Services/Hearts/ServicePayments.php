<?php

declare(strict_types=1);

namespace App\Services\Hearts;

use App\Enums\DutyKind;
use App\Enums\HeartOperationType;
use App\Enums\ReservationStatus;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\HeartReservation;
use App\Models\PartnerRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

final class ServicePayments
{
    public function __construct(private readonly Ledger $ledger, private readonly AvailableBalance $available) {}

    public function requireAvailability(User $payer, int $amount): void
    {
        if ($amount === 0) {
            return;
        }
        $this->available->require($this->ledger->lockWallet($payer), $amount);
    }

    public function reserve(PartnerRequest $request): void
    {
        $this->requireTransaction();
        if ($request->heart_price === 0) {
            return;
        }
        $wallet = $this->ledger->lockWallet($request->author);
        if (HeartReservation::query()->where('partner_request_id', $request->id)->lockForUpdate()->first() !== null) {
            return;
        }
        $this->available->require($wallet, $request->heart_price);
        $reservation = new HeartReservation;
        $reservation->forceFill(['partner_request_id' => $request->id, 'heart_wallet_id' => $wallet->id, 'amount' => $request->heart_price, 'status' => ReservationStatus::Held])->save();
        $this->log($reservation, 'held');
    }

    public function settle(Duty $duty): void
    {
        $this->requireTransaction();
        if ($duty->kind !== DutyKind::Service) {
            return;
        }
        $request = PartnerRequest::query()->whereKey($duty->partner_request_id)->lockForUpdate()->firstOrFail();
        if ($request->heart_price === 0) {
            return;
        }
        $wallet = $this->ledger->lockWallet($request->author);
        $reservation = HeartReservation::query()->where('partner_request_id', $request->id)->lockForUpdate()->firstOrFail();
        if ($reservation->status === ReservationStatus::Settled) {
            return;
        }
        if ($reservation->status !== ReservationStatus::Held || $reservation->amount !== $request->heart_price || $reservation->heart_wallet_id !== $wallet->id) {
            throw new LogicException('Service payment has no valid reservation.');
        }
        $this->ledger->apply($request->author, -$reservation->amount, HeartOperationType::ServicePayment, sprintf('request:%d:service-payment', $request->id), coupleId: $request->couple_id, actorId: $duty->reviewer_id, dutyId: $duty->id);
        $reservation->forceFill(['status' => ReservationStatus::Settled, 'settled_at' => now()])->save();
        $this->log($reservation, 'settled');
    }

    public function release(PartnerRequest $request): void
    {
        $this->requireTransaction();
        if ($request->heart_price === 0) {
            return;
        }
        $this->ledger->lockWallet($request->author);
        $reservation = HeartReservation::query()->where('partner_request_id', $request->id)->lockForUpdate()->first();
        if ($reservation === null || $reservation->status !== ReservationStatus::Held) {
            return;
        }
        $reservation->forceFill(['status' => ReservationStatus::Released, 'released_at' => now()])->save();
        $this->log($reservation, 'released');
    }

    public function releaseCouple(Couple $couple): void
    {
        $requests = PartnerRequest::query()->with('author')->where('couple_id', $couple->id)
            ->whereIn('id', HeartReservation::query()->select('partner_request_id')->where('status', ReservationStatus::Held))
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($requests as $request) {
            $this->release($request);
        }
    }

    private function requireTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Payments require a domain transaction.');
        }
    }

    private function log(HeartReservation $reservation, string $state): void
    {
        DB::afterCommit(fn () => Log::info('hearts.reservation_transitioned', ['reservation_id' => $reservation->id, 'request_id' => $reservation->partner_request_id, 'amount' => $reservation->amount, 'status' => $state]));
    }
}
