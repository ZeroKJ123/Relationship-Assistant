<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgreementProposalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $couple_agreement_id
 * @property int $version
 * @property int $weekly_limit
 * @property ?int $proposed_by_id
 * @property AgreementProposalStatus $status
 * @property ?CarbonImmutable $accepted_at
 * @property Collection<int, AgreementApproval> $approvals
 */
final class AgreementProposal extends Model
{
    /** @return BelongsTo<CoupleAgreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(CoupleAgreement::class, 'couple_agreement_id');
    }

    /** @return HasMany<AgreementApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(AgreementApproval::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['couple_agreement_id' => 'integer', 'version' => 'integer', 'weekly_limit' => 'integer', 'proposed_by_id' => 'integer', 'status' => AgreementProposalStatus::class, 'accepted_at' => 'immutable_datetime'];
    }
}
