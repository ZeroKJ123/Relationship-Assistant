<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CoupleStatus;
use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\CoupleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property CoupleStatus $status
 * @property CarbonImmutable $started_on
 * @property string $timezone
 * @property Currency $currency
 * @property ?CarbonImmutable $ended_at
 */
#[Fillable(['started_on', 'timezone', 'currency'])]
final class Couple extends Model
{
    /** @use HasFactory<CoupleFactory> */
    use HasFactory;

    public const DEFAULT_TIMEZONE = 'Europe/Minsk';

    /** @return HasMany<CoupleMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CoupleMember::class);
    }

    /** @return HasMany<CoupleInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(CoupleInvitation::class);
    }

    /** @return HasOne<CoupleAgreement, $this> */
    public function agreement(): HasOne
    {
        return $this->hasOne(CoupleAgreement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => CoupleStatus::class, 'started_on' => 'immutable_date', 'ended_at' => 'immutable_datetime', 'currency' => Currency::class];
    }
}
