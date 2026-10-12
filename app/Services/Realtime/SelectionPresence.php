<?php

declare(strict_types=1);

namespace App\Services\Realtime;

use App\Models\ChoreDraw;
use App\Models\MovieChoice;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class SelectionPresence
{
    public const START_DELAY_SECONDS = 5;

    private const PRESENCE_SECONDS = 6;

    public function touch(User $actor, ChoreDraw|MovieChoice $selection): void
    {
        Cache::put($this->getKey($selection, $actor->id), true, self::PRESENCE_SECONDS);
    }

    public function areBothPresent(ChoreDraw|MovieChoice $selection): bool
    {
        return Cache::get($this->getKey($selection, $selection->first_user_id)) === true && Cache::get($this->getKey($selection, $selection->second_user_id)) === true;
    }

    private function getKey(ChoreDraw|MovieChoice $selection, int $userId): string
    {
        return sprintf('selection-presence:%s:%d:%d', $selection->getTable(), $selection->id, $userId);
    }
}
