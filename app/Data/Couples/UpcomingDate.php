<?php

declare(strict_types=1);

namespace App\Data\Couples;

use Carbon\CarbonImmutable;

final readonly class UpcomingDate
{
    /**
     * @param  string  $key  устойчивый ключ источника: встроенная дата пары или номер добавленной даты
     * @param  ?int  $years  номер годовщины; null для самого события и разовых дат
     * @param  ?int  $id  номер добавленной даты; null для дат самой пары
     */
    public function __construct(public string $key, public string $title, public CarbonImmutable $date, public int $daysLeft, public ?int $years = null, public ?int $id = null) {}

    public function getCountdown(): string
    {
        return match ($this->daysLeft) {
            0 => __('сегодня'),
            1 => __('завтра'),
            default => __('через :days дн.', ['days' => $this->daysLeft]),
        };
    }
}
