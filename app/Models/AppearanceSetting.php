<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Appearance\AppearanceCatalog;
use Database\Factories\AppearanceSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-import-type Settings from AppearanceCatalog
 *
 * @property int $id
 * @property int $revision
 * @property Settings $settings
 */
final class AppearanceSetting extends Model
{
    /** @use HasFactory<AppearanceSettingFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'settings' => 'array'];
    }
}
