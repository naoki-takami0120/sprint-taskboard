<?php

namespace App\Services;

use App\Models\Sprint;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;

class SprintBurndown
{
    private const TTL_SECONDS = 60 * 60; // a safety net, invalidation does the real work

    /**
     * @return array{sprint_id: int, total_points: int, days: array<int, array{date: string, remaining: int|null}>}
     */
    public function forSprint(Sprint $sprint): array
    {
        $today = now()->toDateString();

        return Cache::remember(
            self::key($sprint->id, $today),
            self::TTL_SECONDS,
            fn () => $this->compute($sprint, $today),
        );
    }

    public static function forget(int $sprintId): void
    {
        Cache::forget(self::key($sprintId, now()->toDateString()));
    }

    private static function key(int $sprintId, string $date): string
    {
        return "burndown:{$sprintId}:{$date}";
    }

    private function compute(Sprint $sprint, string $today): array
    {
        $total = (int) $sprint->tasks()->sum('points');

        $completedByDay = $sprint->tasks()
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->selectRaw('DATE(completed_at) as day, SUM(points) as points')
            ->groupBy('day')
            ->pluck('points', 'day');

        $days = [];

        foreach (CarbonPeriod::create($sprint->start_date, $sprint->end_date) as $date) {
            $day = $date->toDateString();

            $days[] = [
                'date' => $day,
                'remaining' => $day > $today ? null : $total - (int) $completedByDay->filter(fn ($points, $d) => $d <= $day)->sum(),
            ];
        }

        return [
            'sprint_id' => $sprint->id,
            'total_points' => $total,
            'days' => $days,
        ];
    }
}
