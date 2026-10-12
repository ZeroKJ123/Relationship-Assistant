<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AdminAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $admin_id
 * @property ?int $target_user_id
 * @property string $operation
 * @property ?int $response_status
 */
final class AdminAudit extends Model
{
    /** @use HasFactory<AdminAuditFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['admin_id' => 'integer', 'target_user_id' => 'integer', 'response_status' => 'integer'];
    }
}
