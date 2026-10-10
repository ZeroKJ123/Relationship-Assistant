<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConditionType;
use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\RequestConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?int $wishlist_item_id
 * @property ?int $wishlist_version
 * @property ?array<string,string|null> $gift_snapshot
 * @property ?string $amount
 * @property ?Currency $currency
 * @property ?string $payment_details_snapshot
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
#[Hidden(['payment_details_snapshot'])]
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
        return ['wishlist_item_id' => 'integer', 'wishlist_version' => 'integer', 'gift_snapshot' => 'array', 'amount' => 'decimal:2', 'currency' => Currency::class, 'payment_details_snapshot' => 'encrypted', 'partner_request_id' => 'integer', 'proposed_by_id' => 'integer', 'version' => 'integer', 'type' => ConditionType::class, 'due_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
