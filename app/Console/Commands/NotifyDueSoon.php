<?php

namespace App\Console\Commands;

use App\Jobs\SendDueSoonNotice;
use App\Models\Task;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:notify-due-soon')]
#[Description('Queue a notice for tasks that are due today or tomorrow')]
class NotifyDueSoon extends Command
{
    public function handle(): int
    {
        $queued = 0;

        Task::query()
            ->where('status', '!=', 'done')
            ->whereNotNull('assignee_id')
            ->whereNull('due_notified_at')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDay()->toDateString()])
            ->chunkById(200, function ($tasks) use (&$queued) {
                foreach ($tasks as $task) {
                    SendDueSoonNotice::dispatch($task->id);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} notices.");

        return self::SUCCESS;
    }
}
