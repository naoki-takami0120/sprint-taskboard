<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()->latest()->get();

        return response()->json($projects);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $project = DB::transaction(function () use ($request, $validated) {
            $project = Project::create([
                ...$validated,
                'owner_id' => $request->user()->id,
            ]);
            $project->members()->attach($request->user()->id);

            return $project;
        });

        return response()->json($project, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $project = $request->user()->projects()->findOrFail($id);

        return response()->json($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $project = $request->user()->projects()->findOrFail($id);
        if ($project->owner_id !== $request->user()->id) {
            abort(403);
        }
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): Response
    {
        $project = $request->user()->projects()->findOrFail($id);

        if ($project->owner_id !== $request->user()->id) {
            abort(403);
        }
        $project->delete();

        return response()->noContent();
    }
}
