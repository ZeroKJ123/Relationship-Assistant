<?php

declare(strict_types=1);

namespace App\Actions\Duties;

use App\Enums\DutyKind;
use App\Enums\DutyStatus;
use App\Models\Couple;
use App\Models\Duty;
use App\Models\PartnerRequest;
use App\Models\User;
use App\Services\Duties\DutyContext;
use App\Services\Hearts\ApplyDutyEconomy;
use App\Services\Hearts\ServicePayments;
use App\Services\Requests\AgreementAttachments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class VerifyDuty
{
    public function __construct(private readonly DutyContext $context, private readonly ApplyDutyEconomy $economy, private readonly ServicePayments $payments, private readonly AgreementAttachments $attachments) {}

    public function execute(User $actor, Couple $couple, Duty $duty, int $version, bool $isConfirmed): void
    {
        Log::debug('duty.verification_started', ['actor_id' => $actor->id, 'duty_id' => $duty->id, 'expected_version' => $version]);
        DB::transaction(function () use ($actor, $couple, $duty, $version, $isConfirmed): void {
            $current = $this->context->lock($actor, $couple, $duty, 'verify');
            if ($this->context->isRepeated($current, $actor, $version, 'verify', $isConfirmed)) {
                return;
            }
            $this->context->requireVersion($current, $version);
            if ($current->status !== DutyStatus::Reported) {
                $this->context->deny($current, 'not_reported');
            }
            $current->status = $isConfirmed ? DutyStatus::Confirmed : DutyStatus::Disputed;
            $current->reviewed_at = now()->toImmutable();
            $current->version++;
            $current->save();
            $this->attachments->verified($current, $isConfirmed);
            if ($isConfirmed) {
                $this->payments->settle($current);
                $this->economy->reward($current);
            }
            if (! $isConfirmed) {
                $this->economy->falseReport($current);
                if ($current->kind === DutyKind::Service) {
                    $this->payments->release(PartnerRequest::query()->findOrFail($current->partner_request_id));
                }
            }
            $this->context->record($current, $actor, 'verify', $isConfirmed);
        }, attempts: 3);
    }
}
