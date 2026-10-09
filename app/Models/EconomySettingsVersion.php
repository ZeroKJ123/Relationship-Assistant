<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EconomySettingsVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property int $id
 * @property int $version
 * @property array<string,int> $settings
 * @property ?int $created_by_id
 */
final class EconomySettingsVersion extends Model
{
    /** @use HasFactory<EconomySettingsVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Economy versions cannot be changed.'));
        self::deleting(fn () => throw new LogicException('Economy versions cannot be deleted.'));
    }

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'settings' => 'array', 'created_by_id' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
