<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $agreement_proposal_id
 * @property int $user_id
 */
final class AgreementApproval extends Model
{
    /** @return BelongsTo<AgreementProposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(AgreementProposal::class, 'agreement_proposal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['agreement_proposal_id' => 'integer', 'user_id' => 'integer'];
    }
}
