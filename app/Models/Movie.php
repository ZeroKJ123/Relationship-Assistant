<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MovieFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $user_id
 * @property string $identity_hash
 * @property string $title
 * @property ?int $year
 * @property ?string $link
 * @property ?string $photo_path
 * @property ?string $description
 * @property bool $is_favorite
 * @property ?CarbonImmutable $archived_at
 */
final class Movie extends Model
{
    /** @use HasFactory<MovieFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'year' => 'integer', 'title' => 'encrypted', 'description' => 'encrypted', 'link' => 'encrypted', 'is_favorite' => 'boolean', 'archived_at' => 'immutable_datetime'];
    }
}
