<?php

declare(strict_types=1);

namespace App\Services\Mood;

use App\Models\Couple;
use App\Models\MoodEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class MoodDay
{
    public const PAGE_SIZE = 50;

    private const MAXIMUM_POINTS = 200;

    private const CHART_LEFT = 20.0;

    private const CHART_WIDTH = 280.0;

    private const CHART_BOTTOM = 110;

    private const SCORE_STEP = 45;

    public const SCORES = ['bad' => 1, 'low' => 1, 'neutral' => 2, 'good' => 3, 'great' => 3];

    /** @return Builder<MoodEntry> */
    public function getEntries(Couple $couple, int $userId, string $date): Builder
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, $couple->timezone);
        if ($start === null) {
            throw ValidationException::withMessages(['date' => __('Выберите корректный день.')]);
        }

        return MoodEntry::query()->where('couple_id', $couple->id)->where('user_id', $userId)->where('recorded_at', '>=', $start->utc())->where('recorded_at', '<', $start->addDay()->utc());
    }

    /** @return array{points:string,average_y:?float,dots:list<array{x:float,y:int}>} */
    public function getChart(Couple $couple, int $userId, string $date, ?float $average): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, $couple->timezone);
        if ($start === null) {
            throw ValidationException::withMessages(['date' => __('Выберите корректный день.')]);
        }
        $duration = $start->addDay()->getTimestamp() - $start->getTimestamp();
        $entries = $this->getEntries($couple, $userId, $date)->latest('recorded_at')->latest('id')->limit(self::MAXIMUM_POINTS)->get()->reverse();
        $dots = $entries->map(function (MoodEntry $entry) use ($start, $duration): array {
            $x = self::CHART_LEFT + self::CHART_WIDTH * ($entry->recorded_at->getTimestamp() - $start->getTimestamp()) / $duration;
            $y = self::CHART_BOTTOM - (self::SCORES[$entry->level->value] - 1) * self::SCORE_STEP;

            return ['x' => $x, 'y' => $y];
        })->values()->all();
        $points = collect($dots)->map(fn (array $dot): string => sprintf('%.1f,%.1f', $dot['x'], $dot['y']))->implode(' ');

        return ['points' => $points, 'dots' => array_values($dots), 'average_y' => $average === null ? null : self::CHART_BOTTOM - ($average - 1) * self::SCORE_STEP];
    }

    /** @return array{count:int,average:?float} */
    public function getSummary(Couple $couple, int $userId, string $date): array
    {
        $counts = $this->getEntries($couple, $userId, $date)->selectRaw('level, COUNT(*) AS total')->groupBy('level')->toBase()->get();
        $count = 0;
        $sum = 0;
        foreach ($counts as $item) {
            $total = (int) $item->total;
            $count += $total;
            $sum += $total * self::SCORES[(string) $item->level];
        }

        return ['count' => $count, 'average' => $count === 0 ? null : round($sum / $count, 2)];
    }
}
