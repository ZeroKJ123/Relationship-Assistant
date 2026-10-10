<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DishKind;
use Database\Factories\DishFactory;
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
 * @property string $ingredients
 * @property string $estimate
 * @property DishKind $kind
 * @property int $portions
 * @property int $heart_price
 */
#[Hidden(['photo_path', 'photo_hash'])]
final class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory;

    public const MINIMUM_HEART_PRICE = 5;

    public const MAXIMUM_HEART_PRICE = 50;

    public const MAXIMUM_PORTIONS = 20;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'version' => 'integer', 'currency' => Currency::class, 'estimate' => 'decimal:2', 'kind' => DishKind::class, 'portions' => 'integer', 'heart_price' => 'integer'];
    }
}
