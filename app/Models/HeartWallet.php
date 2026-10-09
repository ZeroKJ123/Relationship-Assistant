<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HeartWalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property int $user_id
 * @property int $balance
 */
final class HeartWallet extends Model
{
    /** @use HasFactory<HeartWalletFactory> */
    use HasFactory;

    /** @return HasMany<HeartTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(HeartTransaction::class);
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'balance' => 'integer'];
    }
}
