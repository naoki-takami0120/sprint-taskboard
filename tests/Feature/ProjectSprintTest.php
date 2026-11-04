<?php

namespace Tests\Feature;

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class ProjectSprintTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    public function test_member_sees_the_sprints_of_the_project_newest_first(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $older = Sprint::factory()->create([
            'project_id' => $project->id,
            'title' => 'Older',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-14',
        ]);
        $newer = Sprint::factory()->create([
            'project_id' => $project->id,
            'title' => 'Newer',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-15',
        ]);
        Sprint::factory()->create(); // a sprint of another project

        Sanctum::actingAs($user);

        $this->getJson("/api/projects/{$project->id}/sprints")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.title', 'Newer')
            ->assertJsonPath('data.0.start_date', '2026-11-02')
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_non_member_cannot_see_the_sprints(): void
    {
        $project = $this->projectFor(User::factory()->create());
        Sprint::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/projects/{$project->id}/sprints")->assertNotFound();
    }
}
