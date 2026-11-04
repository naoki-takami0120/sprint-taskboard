<?php

namespace App\Http\Controllers;

use App\Models\Sprint;
use App\Services\SprintBurndown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintBurndownController extends Controller
{
    public function show(Request $request, string $sprint, SprintBurndown $burndown): JsonResponse
    {
        $found = Sprint::visibleTo($request->user())->findOrFail($sprint);

        return response()->json(['data' => $burndown->forSprint($found)]);
    }
}
