<?php

namespace App\Http\Controllers;

use App\Http\Resources\SprintResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectSprintController extends Controller
{
    public function index(Request $request, string $project): AnonymousResourceCollection
    {
        $found = $request->user()->projects()->findOrFail($project);

        return SprintResource::collection(
            $found->sprints()->orderByDesc('start_date')->orderByDesc('id')->get()
        );
    }
}
