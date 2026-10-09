<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\HeartReservationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $partner_request_id
 * @property int $heart_wallet_id
 * @property int $amount
 * @property ReservationStatus $status
 */
final class HeartReservation extends Model
{
    /** @use HasFactory<HeartReservationFactory> */
    use HasFactory;

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['partner_request_id' => 'integer', 'heart_wallet_id' => 'integer', 'amount' => 'integer', 'status' => ReservationStatus::class, 'settled_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime'];
    }
}
