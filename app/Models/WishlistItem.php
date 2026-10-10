<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\WishlistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $last_operation_key
 * @property string $operation_key
 * @property string $title
 * @property Currency $currency
 * @property ?string $photo_path
 * @property ?string $photo_hash
 * @property int $version
 * @property User $user
 * @property ?string $description
 * @property string $price
 * @property ?CarbonImmutable $archived_at
 * @property ?CarbonImmutable $received_at
 * @property ?int $claimed_condition_id
 */
#[Hidden(['photo_path', 'photo_hash'])]
final class WishlistItem extends Model
{
    /** @use HasFactory<WishlistItemFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'version' => 'integer', 'currency' => Currency::class, 'price' => 'decimal:2', 'archived_at' => 'immutable_datetime', 'received_at' => 'immutable_datetime', 'claimed_condition_id' => 'integer'];
    }
}
