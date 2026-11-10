<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\SprintBurndown;
use Illuminate\Support\Facades\DB;

class TaskObserver
{
    public function updating(Task $task): void
    {
        // The old sprint also changes when a task moves out of it.
        if ($task->isDirty('sprint_id')) {
            $this->forgetAfterCommit($task->getOriginal('sprint_id'));
        }

        // A new due date or a new assignee deserves a new notice.
        if ($task->isDirty(['due_date', 'assignee_id'])) {
            $task->due_notified_at = null;
        }
    }

    public function saved(Task $task): void
    {
        $this->forgetAfterCommit($task->sprint_id);
    }

    public function deleted(Task $task): void
    {
        $this->forgetAfterCommit($task->sprint_id);
    }

    private function forgetAfterCommit(?int $sprintId): void
    {
        if ($sprintId === null) {
            return;
        }

        DB::afterCommit(fn () => SprintBurndown::forget($sprintId));
    }
}
