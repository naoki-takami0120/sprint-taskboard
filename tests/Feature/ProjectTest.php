<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $owner): Project
    {
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $project->members()->attach($owner->id);

        return $project;
    }

    public function test_guest_cannot_list_projects(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
    }

    public function test_index_returns_only_projects_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $mine = $this->projectFor($user);

        $this->projectFor(User::factory()->create());
        Sanctum::actingAs($user);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_user_can_create_project(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/projects', [
            'title' => 'New Project',
            'description' => 'About the project',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New Project');

        $this->assertDatabaseHas('projects', [
            'title' => 'New Project',
            'owner_id' => $user->id,
        ]);
    }

    public function test_creator_becomes_a_member(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/projects', ['title' => 'New Project']);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $response->json('data.id'),
            'user_id' => $user->id,
        ]);
    }

    public function test_create_requires_title(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/projects', ['description' => 'No title'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_owner_id_in_request_is_ignored(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/projects', [
            'title' => 'New Project',
            'owner_id' => $other->id,
        ])->assertCreated();

        $this->assertDatabaseHas('projects', [
            'title' => 'New Project',
            'owner_id' => $user->id,
        ]);
    }

    public function test_member_can_view_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);

        $member = User::factory()->create();
        $project->members()->attach($member->id);

        Sanctum::actingAs($member);
        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id);
    }

    public function test_non_member_cannot_view_project(): void
    {
        $project = $this->projectFor(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
    }

    public function test_owner_can_update_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);
        Sanctum::actingAs($owner);

        $this->putJson("/api/projects/{$project->id}", [
            'title' => 'Updated title',
        ])->assertOk();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Updated title',
        ]);
    }

    public function test_member_who_is_not_owner_cannot_update_project(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $member = User::factory()->create();
        $project->members()->attach($member->id);

        Sanctum::actingAs($member);
        $this->putJson("/api/projects/{$project->id}", ['title' => 'Hacked'])
            ->assertForbidden();
        $this->assertDatabaseMissing('projects', ['title' => 'Hacked']);
    }

    public function test_non_member_cannot_update_project(): void
    {
        $project = $this->projectFor(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/projects/{$project->id}", ['title' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_owner_can_delete_project(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/projects/{$project->id}")->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_member_who_is_not_owner_cannot_delete_project(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $member = User::factory()->create();
        $project->members()->attach($member->id);
        Sanctum::actingAs($member);

        $this->deleteJson("/api/projects/{$project->id}")->assertForbidden();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_non_member_cannnot_delete_project(): void
    {
        $project = $this->projectFor(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/projects/{$project->id}")->assertNotFound();
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }
}
