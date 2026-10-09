<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $couple_agreement_id
 * @property int $user_id
 * @property int $weekly_limit
 */
final class AgreementAnswer extends Model
{
    /** @return BelongsTo<CoupleAgreement, $this> */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(CoupleAgreement::class, 'couple_agreement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['couple_agreement_id' => 'integer', 'user_id' => 'integer', 'weekly_limit' => 'integer'];
    }
}
