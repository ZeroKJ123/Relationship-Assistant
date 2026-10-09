<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EconomySettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property ?int $current_version_id */
final class EconomySetting extends Model
{
    /** @use HasFactory<EconomySettingFactory> */
    use HasFactory;

    public const SINGLETON_ID = 1;

    public $timestamps = false;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['current_version_id' => 'integer'];
    }
}
