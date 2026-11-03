<?php

namespace App\Services;

use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Redis;

class TaskRanking
{
    private const TTL_SECONDS = 60 * 60 * 24 * 7 * 8; // 8 weeks

    public function key(int $projectId, CarbonInterface $week): string
    {
        return sprintf('ranking:%d:%s', $projectId, $week->format('o-\WW'));
    }

    public function add(Task $task): void
    {
        $this->change($task, +1);
    }

    public function remove(Task $task): void
    {
        $this->change($task, -1);
    }

    private function change(Task $task, int $direction): void
    {
        if ($task->assignee_id === null || $task->completed_at === null) {
            return;
        }

        $key = $this->key($task->project_id, $task->completed_at);

        Redis::zincrby($key, $direction * $task->points, (string) $task->assignee_id);
        Redis::expire($key, self::TTL_SECONDS);
    }

    /**
     * @return array<int, array{user_id: int, points: int}>
     */
    public function top(int $projectId, CarbonInterface $week, int $limit = 10): array
    {
        $rows = Redis::zrevrange($this->key($projectId, $week), 0, $limit - 1, ['withscores' => true]);

        return collect($rows)
            ->map(fn ($score, $userId) => ['user_id' => (int) $userId, 'points' => (int) $score])
            ->values()
            ->all();
    }
}
