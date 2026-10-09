<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\Gender;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property ?string $login
 * @property string $name
 * @property ?string $password
 * @property ?Gender $gender
 * @property ?string $locale
 * @property ?Currency $currency
 * @property bool $is_admin
 * @property ?string $avatar_path
 * @property ?string $payment_details
 * @property ?list<string> $recovery_codes
 */
#[Fillable(['name', 'login', 'password', 'gender', 'locale', 'currency', 'payment_details'])]
#[Hidden(['email', 'password', 'remember_token', 'payment_details', 'recovery_codes', 'is_admin', 'avatar_path'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return HasOne<TelegramAccount, $this> */
    public function telegramAccount(): HasOne
    {
        return $this->hasOne(TelegramAccount::class);
    }

    /** @return HasMany<CoupleMember, $this> */
    public function coupleMemberships(): HasMany
    {
        return $this->hasMany(CoupleMember::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'gender' => Gender::class,
            'currency' => Currency::class,
            'is_admin' => 'boolean',
            'payment_details' => 'encrypted',
            'recovery_codes' => 'encrypted:array',
        ];
    }
}
