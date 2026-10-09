<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConditionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_request_id
 * @property int $proposed_by_id
 * @property int $version
 * @property ConditionType $type
 * @property string $description
 * @property CarbonImmutable $due_at
 * @property ?CarbonImmutable $accepted_at
 */
final class RequestCondition extends Model
{
    /** @return BelongsTo<PartnerRequest, $this> */
    public function partnerRequest(): BelongsTo
    {
        return $this->belongsTo(PartnerRequest::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['partner_request_id' => 'integer', 'proposed_by_id' => 'integer', 'version' => 'integer', 'type' => ConditionType::class, 'due_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
