<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskStatusController extends Controller
{
    public function update(UpdateTaskStatusRequest $request): TaskResource
    {
        $newStatus = TaskStatus::from($request->validated('status'));

        $task = DB::transaction(function () use ($request, $newStatus) {
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

            return $task;
        });

        return new TaskResource($task);
    }
}
