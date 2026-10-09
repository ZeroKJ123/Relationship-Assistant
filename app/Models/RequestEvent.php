<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $partner_request_id
 * @property int $actor_id
 * @property string $type
 * @property RequestStatus $status
 * @property int $version
 */
final class RequestEvent extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['partner_request_id' => 'integer', 'actor_id' => 'integer', 'status' => RequestStatus::class, 'version' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
