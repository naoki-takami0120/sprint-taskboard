<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class TaskStatusTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    public function test_change_status_from_todo_to_in_progress(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $task = Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status", [
            'status' => 'in_progress',
        ])->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('task_status_logs', [
            'task_id' => $task->id,
            'from_status' => 'todo',
            'to_status' => 'in_progress',
            'changed_by' => $user->id,
        ]);
    }

    public function test_status_change_writes_one_log_row(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $task = Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertOk();
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'done'])->assertOk();

        $this->assertDatabaseCount('task_status_logs', 2);
    }

    public function test_completed_at_is_set_when_task_is_done(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $task = Task::factory()->inProgress()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'done'])->assertOk();

        $this->assertNotNull($task->refresh()->completed_at);
    }

    public function test_completed_at_is_cleared_when_task_is_reopened(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $task = Task::factory()->done()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])->assertOk();

        $this->assertNull($task->refresh()->completed_at);
    }

    public function test_cannot_move_a_done_task_back_to_todo(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $task = Task::factory()->done()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'todo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'done']);
        $this->assertDatabaseCount('task_status_logs', 0);
    }

    public function test_same_status_does_nothing(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $task = Task::factory()->inProgress()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
        $this->assertDatabaseCount('task_status_logs', 0);
    }

    public function test_cannot_set_an_unknown_status(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $task = Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'archived'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
        $this->assertDatabaseCount('task_status_logs', 0);
    }

    public function test_status_is_required(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $task = Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
        $this->assertDatabaseCount('task_status_logs', 0);
    }

    public function test_non_member_cannot_change_status(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $task = Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
        $this->assertDatabaseCount('task_status_logs', 0);
    }

    public function test_guest_cannot_change_status(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertUnauthorized();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
        $this->assertDatabaseCount('task_status_logs', 0);
    }

    public function test_cannot_skip_from_todo_to_done(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $task = Task::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'done'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
        $this->assertDatabaseCount('task_status_logs', 0);
    }
}
