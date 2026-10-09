<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConditionType;
use Carbon\CarbonImmutable;
use Database\Factories\RequestConditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?string $method_kind
 * @property ?string $method_name
 * @property ?string $sushi_size
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
    /** @use HasFactory<RequestConditionFactory> */
    use HasFactory;

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
