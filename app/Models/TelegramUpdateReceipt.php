<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TelegramUpdateReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $update_id
 * @property string $payload_hash
 * @property ?array<string, mixed> $reply
 */
#[Hidden(['payload_hash', 'reply'])]
final class TelegramUpdateReceipt extends Model
{
    /** @use HasFactory<TelegramUpdateReceiptFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['update_id' => 'integer', 'reply' => 'encrypted:array'];
    }
}
