<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TelegramAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $telegram_id */
#[Fillable(['user_id', 'telegram_id', 'chat_id'])]
#[Hidden(['telegram_id', 'chat_id'])]
final class TelegramAccount extends Model
{
    /** @use HasFactory<TelegramAccountFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
