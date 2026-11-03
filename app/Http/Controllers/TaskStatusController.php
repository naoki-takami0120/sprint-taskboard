<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Services\TaskRanking;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskStatusController extends Controller
{
    public function update(UpdateTaskStatusRequest $request, TaskRanking $ranking): TaskResource
    {
        $newStatus = TaskStatus::from($request->validated('status'));

        $task = DB::transaction(function () use ($request, $newStatus, $ranking) {
            $task = Task::whereKey($request->task()->id)->lockForUpdate()->firstOrFail();

            if ($task->status === $newStatus) {
                return $task;
            }
            if (! $task->status->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move a task from {$task->status->value} to {$newStatus->value}.",
                ]);
            }
            $from = $task->status;
            $before = clone $task;

            $task->update([
                'status' => $newStatus,
                'completed_at' => $newStatus === TaskStatus::Done ? now() : null,
            ]);

            TaskStatusLog::create([
                'task_id' => $task->id,
                'from_status' => $from->value,
                'to_status' => $newStatus->value,
                'changed_by' => $request->user()->id,
            ]);

            DB::afterCommit(function () use ($ranking, $task, $from, $newStatus) {
                if ($from === TaskStatus::Done) {
                    $ranking->remove($task);
                }

                if ($newStatus === TaskStatus::Done) {
                    $ranking->add($task);
                }
            });

            return $task;
        });

        return new TaskResource($task);
    }
}
