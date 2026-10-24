<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $owner): Project
    {
        $project = Project::factory()->create(['owner_id' => $owner->id]);
        $project->members()->attach($owner->id);

        return $project;
    }

    public function test_guest_cannot_list_tasks(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $this->getJson("/api/projects/{$project->id}/tasks")->assertUnauthorized();
    }

    public function test_member_can_list_tasks(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $member = User::factory()->create();
        $project->members()->attach($member->id);
        Task::factory(3)->create(['project_id' => $project->id]);

        Sanctum::actingAs($member);

        $this->getJson("/api/projects/{$project->id}/tasks")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_contains_only_tasks_of_the_project(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        Task::factory(3)->create(['project_id' => $project->id]);

        $otherProject = $this->projectFor(User::factory()->create());
        Task::factory(2)->create(['project_id' => $otherProject->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/projects/{$project->id}/tasks")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_non_member_cannot_list_tasks(): void
    {
        $project = $this->projectFor(User::factory()->create());
        Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/projects/{$project->id}/tasks")->assertNotFound();
    }

    public function test_member_can_create_task(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $member = User::factory()->create();
        $project->members()->attach($member->id);

        Sanctum::actingAs($member);
        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'New task'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'todo');

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'New task',
            'status' => 'todo',
        ]);
    }

    public function test_user_cannot_create_task_without_title(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Sanctum::actingAs($user);
        $this->postJson("/api/projects/{$project->id}/tasks", ['description' => 'No title'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_status_in_request_is_ignored_on_create(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Sanctum::actingAs($user);
        $this->postJson("/api/projects/{$project->id}/tasks", ['title' => 'New task', 'status' => 'done'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'todo');

        $this->assertDatabaseHas('tasks', ['title' => 'New task', 'status' => 'todo']);
    }

    public function test_cannot_assign_sprint_of_another_project(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherSprint = Sprint::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'sprint_id' => $otherSprint->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sprint_id']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_cannot_assign_non_member_as_assignee(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $nonMember = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
            'assignee_id' => $nonMember->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assignee_id']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_non_member_cannot_create_task(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $nonMember = User::factory()->create();

        Sanctum::actingAs($nonMember);

        $this->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'New task',
        ])
            ->assertNotFound();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_member_can_view_task(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $member = User::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        $project->members()->attach($member->id);

        Sanctum::actingAs($member);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.title', $task->title);
    }

    public function test_non_member_cannot_view_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->projectFor(User::factory()->create())->id,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/tasks/{$task->id}")->assertNotFound();
    }

    public function test_member_can_update_task(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $member = User::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Original']);
        $project->members()->attach($member->id);

        Sanctum::actingAs($member);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Updated']);
    }

    public function test_can_update_description_without_title(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Original']);

        Sanctum::actingAs($user);

        $this->putJson("/api/tasks/{$task->id}", ['description' => 'New description'])
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Original',
            'description' => 'New description',
        ]);
    }

    public function test_cannot_blank_the_title(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Original']);

        Sanctum::actingAs($user);

        $this->putJson("/api/tasks/{$task->id}", ['title' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original']);
    }

    public function test_status_in_request_is_ignored_on_update(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Original']);

        Sanctum::actingAs($user);

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Updated', 'status' => 'done'])
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated',
            'status' => 'todo',
        ]);
    }

    public function test_non_member_cannot_update_task(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Original']);

        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Hacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original']);
    }

    public function test_member_can_delete_task(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $member = User::factory()->create();
        $project->members()->attach($member->id);
        $task = Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_non_member_cannot_delete_task(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $task = Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/tasks/{$task->id}")->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }
}
