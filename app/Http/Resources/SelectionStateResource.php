<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ChoreDraw;
use App\Models\MovieChoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SelectionStateResource extends JsonResource
{
    public function __construct(private readonly ChoreDraw|MovieChoice $selection)
    {
        parent::__construct($selection);
    }

    /** @return array{status:string, server_time:int, spin_started_at:?int} */
    public function toArray(Request $request): array
    {
        return ['status' => $this->selection->status->value, 'server_time' => now()->getTimestampMs(), 'spin_started_at' => $this->selection->spin_started_at?->getTimestampMs()];
    }
}
