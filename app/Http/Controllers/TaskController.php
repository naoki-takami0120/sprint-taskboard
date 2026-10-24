<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        $project = $request->user()->projects()->findOrFail($projectId);
        $tasks = $project->tasks()->latest()->get();

        return response()->json($tasks);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $request->project()->tasks()->create($request->validated());

        return response()->json($task->refresh(), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $task = $this->findTask($request, $id);

        return response()->json($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request): JsonResponse
    {
        $task = $request->task();
        $task->update($request->validated());

        return response()->json($task);
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
