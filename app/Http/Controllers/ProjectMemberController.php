<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProjectMemberController extends Controller
{
    public function index(Request $request, string $project): AnonymousResourceCollection
    {
        $found = $request->user()->projects()->findOrFail($project);

        return UserResource::collection($found->members);
    }

    public function store(StoreProjectMemberRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();
        $request->project()->members()->attach($user->id);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $project, string $user): Response
    {
        $found = $request->user()->projects()->findOrFail($project);
        Gate::authorize('manageMembers', $found);
        $member = $found->members()->findOrFail($user);

        if ($member->id === $found->owner_id) {
            abort(422, 'The owner cannot be removed.');
        }

        DB::transaction(function () use ($found, $member) {
            $found->tasks()->where('assignee_id', $member->id)->update(['assignee_id' => null]);
            $found->members()->detach($member->id);
        });

        return response()->noContent();
    }
}
