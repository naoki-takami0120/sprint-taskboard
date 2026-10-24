<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string $projectId): AnonymousResourceCollection
    {
        $project = $request->user()->projects()->findOrFail($projectId);
        $tasks = $project->tasks()->latest()->get();

        return TaskResource::collection($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $request->project()->tasks()->create($request->validated());

        return (new TaskResource($task->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): TaskResource
    {
        $task = $this->findTask($request, $id);

        return new TaskResource($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request): TaskResource
    {
        $task = $request->task();
        $task->update($request->validated());

        return new TaskResource($task);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): Response
    {
        $task = $this->findTask($request, $id);
        $task->delete();

        return response()->noContent();
    }

    private function findTask(Request $request, string $id): Task
    {
        return Task::visibleTo($request->user())->findOrFail($id);
    }
}
