<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationKind;
use Carbon\CarbonImmutable;
use Database\Factories\DateInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_request_id
 * @property InvitationKind $kind
 * @property string $title
 * @property CarbonImmutable $starts_at
 * @property ?string $description
 * @property ?string $dress_code
 * @property PartnerRequest $partnerRequest
 */
final class DateInvitation extends Model
{
    /** @use HasFactory<DateInvitationFactory> */
    use HasFactory;

    /** @return BelongsTo<PartnerRequest,$this> */
    public function partnerRequest(): BelongsTo
    {
        return $this->belongsTo(PartnerRequest::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['partner_request_id' => 'integer', 'kind' => InvitationKind::class, 'starts_at' => 'immutable_datetime'];
    }
}
