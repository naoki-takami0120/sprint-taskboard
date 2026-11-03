<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\TaskRanking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

#[Signature('ranking:rebuild')]
#[Description('Rebuild the weekly ranking in Redis from the tasks table')]
class RebuildRankings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TaskRanking $ranking): int
    {
        $keys = Redis::keys('ranking:*') ?: [];
        if ($keys !== []) {
            Redis::del(...$keys);
        }
        Task::query()
            ->where('status', 'done')
            ->whereNotNull('assignee_id')
            ->whereNotNull('completed_at')
            ->chunkById(500, function ($tasks) use ($ranking) {
                foreach ($tasks as $task) {
                    $ranking->add($task);
                }
            });

        $this->info('Rankings rebuilt.');

        return self::SUCCESS;
    }
}
