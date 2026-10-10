<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\GuestVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_request_id
 * @property list<array{id:int,version:int,name:string}> $guests_snapshot
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $duration_minutes
 * @property string $reason
 * @property PartnerRequest $partnerRequest
 */
final class GuestVisit extends Model
{
    /** @use HasFactory<GuestVisitFactory> */
    use HasFactory;

    public const MAXIMUM_GUESTS = 20;

    public const MINIMUM_DURATION_MINUTES = 15;

    public const MAXIMUM_DURATION_MINUTES = 1440;

    /** @return BelongsTo<PartnerRequest,$this> */
    public function partnerRequest(): BelongsTo
    {
        return $this->belongsTo(PartnerRequest::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['partner_request_id' => 'integer', 'guests_snapshot' => 'array', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'duration_minutes' => 'integer'];
    }
}
