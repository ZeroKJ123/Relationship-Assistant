<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $couple_id
 * @property int $current_version
 * @property ?int $active_version
 * @property ?int $active_weekly_limit
 * @property Collection<int, AgreementAnswer> $answers
 */
final class CoupleAgreement extends Model
{
    /** @return BelongsTo<Couple, $this> */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /** @return HasMany<AgreementAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AgreementAnswer::class);
    }

    /** @return HasMany<AgreementProposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(AgreementProposal::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'current_version' => 'integer', 'active_version' => 'integer', 'active_weekly_limit' => 'integer'];
    }
}
