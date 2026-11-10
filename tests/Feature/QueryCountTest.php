<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CountsQueries;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class QueryCountTest extends TestCase
{
    use CountsQueries, CreatesProjects, RefreshDatabase;

    private function addTasksWithAssignees(Project $project, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $assignee = User::factory()->create();
            $project->members()->attach($assignee->id);

            Task::factory()->create([
                'project_id' => $project->id,
                'assignee_id' => $assignee->id,
            ]);
        }
    }

    public function test_task_list_query_count_does_not_grow_with_the_number_of_tasks(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        Sanctum::actingAs($user);

        $this->addTasksWithAssignees($project, 2);
        $few = $this->countQueries(
            fn () => $this->getJson("/api/projects/{$project->id}/tasks")->assertOk(),
        );

        $this->addTasksWithAssignees($project, 13);
        $many = $this->countQueries(
            fn () => $this->getJson("/api/projects/{$project->id}/tasks")->assertOk(),
        );

        $this->assertSame($few, $many);
    }
}
