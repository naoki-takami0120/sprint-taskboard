<?php

namespace Tests\Concerns;

use App\Models\Project;
use App\Models\User;

trait CreatesProjects
{
    private function projectFor(User $owner): Project
    {
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $project->members()->attach($owner->id);

        return $project;
    }
}
