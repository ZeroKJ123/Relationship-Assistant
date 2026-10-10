<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MovieVoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $couple_id
 * @property int $user_id
 * @property string $identity_hash
 * @property bool $liked
 */
final class MovieVote extends Model
{
    /** @use HasFactory<MovieVoteFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['couple_id' => 'integer', 'user_id' => 'integer', 'liked' => 'boolean'];
    }
}
