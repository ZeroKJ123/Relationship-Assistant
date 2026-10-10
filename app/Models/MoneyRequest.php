<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\MoneyRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $partner_request_id
 * @property string $amount
 * @property Currency $currency
 * @property ?string $payment_details_snapshot
 * @property PartnerRequest $partnerRequest
 */
#[Hidden(['payment_details_snapshot'])]
final class MoneyRequest extends Model
{
    /** @use HasFactory<MoneyRequestFactory> */
    use HasFactory;

    /** @return BelongsTo<PartnerRequest, $this> */
    public function partnerRequest(): BelongsTo
    {
        return $this->belongsTo(PartnerRequest::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['partner_request_id' => 'integer', 'amount' => 'decimal:2', 'currency' => Currency::class, 'payment_details_snapshot' => 'encrypted'];
    }
}
