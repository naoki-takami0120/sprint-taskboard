<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class ProjectMemberTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    public function test_member_can_list_members(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $members = User::factory(3)->create();
        $project->members()->attach($members->pluck('id'));

        Sanctum::actingAs($members->first());
        $this->getJson("/api/projects/{$project->id}/members")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonFragment(['email' => $members->first()->email]);
    }

    public function test_nonmember_cannot_list_members(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);

        $this->getJson("/api/projects/{$project->id}/members")
            ->assertNotFound();
    }

    public function test_owner_can_add_a_member(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);
        $newUser = User::factory()->create();

        Sanctum::actingAs($owner);
        $this->postJson("/api/projects/{$project->id}/members", [
            'email' => $newUser->email,
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', $newUser->id);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $newUser->id,
        ]);
    }

    public function test_non_owner_cannot_add_a_member(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $member = User::factory()->create();
        $project->members()->attach($member->id);
        $newUser = User::factory()->create();

        Sanctum::actingAs($member);
        $this->postJson("/api/projects/{$project->id}/members", [
            'email' => $newUser->email,
        ])->assertForbidden();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $newUser->id,
        ]);
    }

    public function test_non_member_cannot_add_a_member(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $nonMember = User::factory()->create();
        $newUser = User::factory()->create();

        Sanctum::actingAs($nonMember);
        $this->postJson("/api/projects/{$project->id}/members", [
            'email' => $newUser->email,
        ])->assertNotFound();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $newUser->id,
        ]);
    }

    public function test_cannot_add_a_member_with_unknown_email(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/projects/{$project->id}/members", [
            'email' => 'nobody@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('project_user', 1);
    }

    public function test_cannot_add_an_existing_member(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);
        $member = User::factory()->create();
        $project->members()->attach($member->id);

        Sanctum::actingAs($owner);
        $this->postJson("/api/projects/{$project->id}/members", [
            'email' => $member->email,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('project_user', 2);
    }

    public function test_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);
        $member = User::factory()->create();
        $project->members()->attach($member->id);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/projects/{$project->id}/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_removing_a_member_unassigns_their_task(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);
        $member = User::factory()->create();
        $project->members()->attach($member->id);

        $task = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $member->id]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/projects/{$project->id}/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $member->id,
        ]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assignee_id' => null]);
    }

    public function test_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $project = $this->projectFor($owner);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/projects/{$project->id}/members/{$owner->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_non_owner_cannot_remove_a_member(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $member = User::factory()->create();
        $otherMember = User::factory()->create();
        $project->members()->attach([$member->id, $otherMember->id]);

        Sanctum::actingAs($member);
        $this->deleteJson("/api/projects/{$project->id}/members/{$otherMember->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $otherMember->id,
        ]);
    }
}
