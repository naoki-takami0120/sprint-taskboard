<?php

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskDueSoon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDueSoonNotice implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 300; // seconds the unique lock is held at most

    public function __construct(public int $taskId) {}

    public function uniqueId(): string
    {
        return (string) $this->taskId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $task = Task::with('assignee')->find($this->taskId);

        // The task may have changed since this job was queued.
        if ($task === null || $this->shouldSkip($task)) {
            return;
        }

        $task->assignee->notify(new TaskDueSoon($task));

        $task->due_notified_at = now();
        $task->save();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Due-soon notice failed', [
            'task_id' => $this->taskId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function shouldSkip(?Task $task): bool
    {
        return $task->status === TaskStatus::Done
            || $task->assignee === null
            || $task->due_notified_at !== null;
    }
}
