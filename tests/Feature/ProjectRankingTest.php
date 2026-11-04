<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskRanking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesProjects;
use Tests\Concerns\FlushesRedis;
use Tests\TestCase;

class ProjectRankingTest extends TestCase
{
    use CreatesProjects, FlushesRedis, RefreshDatabase;

    public function test_member_can_see_the_ranking(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $project = $this->projectFor($owner);

        $member = User::factory()->create(['name' => 'Member']);
        $project->members()->attach($member->id);

        $ranking = app(TaskRanking::class);
        $ranking->add(Task::factory()->done()->create([
            'project_id' => $project->id,
            'assignee_id' => $owner->id,
            'points' => 5,
        ]));
        $ranking->add(Task::factory()->done()->create([
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'points' => 8,
        ]));

        Sanctum::actingAs($owner);

        $this->getJson("/api/projects/{$project->id}/ranking")
            ->assertOk()
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.entries.0.user_id', $member->id)
            ->assertJsonPath('data.entries.0.name', 'Member')
            ->assertJsonPath('data.entries.0.points', 8)
            ->assertJsonPath('data.entries.1.user_id', $owner->id)
            ->assertJsonPath('data.entries.1.name', 'Owner')
            ->assertJsonPath('data.entries.1.points', 5);
    }

    public function test_non_member_cannot_see_the_ranking(): void
    {
        $project = $this->projectFor(User::factory()->create());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/projects/{$project->id}/ranking")->assertNotFound();
    }

    public function test_guest_cannot_see_the_ranking(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $this->getJson("/api/projects/{$project->id}/ranking")->assertUnauthorized();
    }
}
