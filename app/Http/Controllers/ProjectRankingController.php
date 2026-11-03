<?php

namespace App\Http\Controllers;

use App\Services\TaskRanking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectRankingController extends Controller
{
    public function show(Request $request, string $project, TaskRanking $ranking): JsonResponse
    {
        $found = $request->user()->projects()->findOrFail($project);

        $week = now();
        $top = $ranking->top($found->id, $week);

        $names = $found->members()
            ->whereIn('users.id', collect($top)->pluck('user_id'))
            ->pluck('users.name', 'users.id');

        return response()->json([
            'data' => [
                'week' => $week->format('o-\WW'),
                'entries' => collect($top)->map(fn ($row) => [
                    'user_id' => $row['user_id'],
                    'name' => $names[$row['user_id']] ?? 'Unknown',
                    'points' => $row['points'],
                ])->all(),
            ],
        ]);
    }
}
