<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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
    public function store(Request $request, string $projectId): JsonResponse
    {
        $project = $request->user()->projects()->findOrFail($projectId);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sprint_id' => ['nullable', Rule::exists('sprints', 'id')->where('project_id', $project->id)],
            'assignee_id' => ['nullable', Rule::exists('project_user', 'user_id')->where('project_id', $project->id)],
            'due_date' => ['nullable', 'date'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $task = $project->tasks()->create($validated);

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
    public function update(Request $request, string $id): JsonResponse
    {
        $task = $this->findTask($request, $id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sprint_id' => ['nullable', Rule::exists('sprints', 'id')->where('project_id', $task->project_id)],
            'assignee_id' => ['nullable', Rule::exists('project_user', 'user_id')->where('project_id', $task->project_id)],
            'due_date' => ['nullable', 'date'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $task->update($validated);

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
        return Task::whereIn('project_id', $request->user()->projects()->select('projects.id'))->findOrFail($id);
    }
}
