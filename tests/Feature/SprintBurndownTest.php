<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use App\Services\SprintBurndown;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesProjects;
use Tests\Concerns\FlushesRedis;
use Tests\TestCase;

class SprintBurndownTest extends TestCase
{
    use CreatesProjects, FlushesRedis, RefreshDatabase;

    public function test_remaining_points_fall_on_the_day_tasks_are_completed(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));

        $project = Project::factory()->create();
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
        ]);

        Task::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 5,
            'completed_at' => '2026-11-02 10:00:00',
        ]);
        Task::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 3,
            'completed_at' => '2026-11-04 09:00:00',
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 2,
        ]);

        $result = app(SprintBurndown::class)->forSprint($sprint);

        $this->assertSame(10, $result['total_points']);
        $this->assertSame([5, 5, 2, null, null], array_column($result['days'], 'remaining'));
    }

    public function test_tasks_of_other_sprints_and_without_a_sprint_are_not_counted(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));

        $project = Project::factory()->create();
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
        ]);
        $otherSprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
        ]);

        Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 4,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $otherSprint->id,
            'points' => 7,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => null,
            'points' => 9,
        ]);
        Task::factory()->done()->create([
            'project_id' => $project->id,
            'sprint_id' => $otherSprint->id,
            'points' => 6,
        ]);

        $result = app(SprintBurndown::class)->forSprint($sprint);

        $this->assertSame(4, $result['total_points']);
        $this->assertSame([4, 4, 4, null, null], array_column($result['days'], 'remaining'));
    }

    public function test_second_call_is_served_from_the_cache(): void
    {
        $project = Project::factory()->create();
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 3,
        ]);

        $service = app(SprintBurndown::class);

        DB::enableQueryLog();
        $first = $service->forSprint($sprint);
        $this->assertNotEmpty(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $service->forSprint($sprint);

        $this->assertSame([], DB::getQueryLog());
        $this->assertSame($first, $second);
    }

    public function test_changing_a_task_status_refreshes_the_burndown(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));

        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
        ]);
        $task = Task::factory()->inProgress()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 5,
        ]);

        $service = app(SprintBurndown::class);
        $this->assertSame(
            [5, 5, 5, null, null],
            array_column($service->forSprint($sprint)['days'], 'remaining'),
        );

        Sanctum::actingAs($user);
        $this->patchJson("/api/tasks/{$task->id}/status", ['status' => 'done'])->assertOk();

        $this->assertSame(
            [5, 5, 0, null, null],
            array_column($service->forSprint($sprint)['days'], 'remaining'),
        );
    }

    public function test_moving_a_task_to_another_sprint_refreshes_both_sprints(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));

        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $from = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
        ]);
        $to = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $from->id,
            'points' => 5,
        ]);

        $service = app(SprintBurndown::class);
        $this->assertSame(5, $service->forSprint($from)['total_points']);
        $this->assertSame(0, $service->forSprint($to)['total_points']);

        Sanctum::actingAs($user);
        $this->putJson("/api/tasks/{$task->id}", ['sprint_id' => $to->id])->assertOk();

        $this->assertSame(0, $service->forSprint($from)['total_points']);
        $this->assertSame(5, $service->forSprint($to)['total_points']);
    }

    public function test_deleting_a_task_refreshes_the_total(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 5,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 3,
        ]);

        $service = app(SprintBurndown::class);
        $this->assertSame(8, $service->forSprint($sprint)['total_points']);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

        $this->assertSame(3, $service->forSprint($sprint)['total_points']);
    }

    public function test_member_can_see_the_burndown(): void
    {
        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));

        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $sprint = Sprint::factory()->create([
            'project_id' => $project->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'points' => 5,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/sprints/{$sprint->id}/burndown")
            ->assertOk()
            ->assertJsonPath('data.sprint_id', $sprint->id)
            ->assertJsonPath('data.total_points', 5)
            ->assertJsonCount(5, 'data.days')
            ->assertJsonPath('data.days.0.date', '2026-11-02')
            ->assertJsonPath('data.days.0.remaining', 5)
            ->assertJsonPath('data.days.4.remaining', null);
    }

    public function test_non_member_cannot_see_the_burndown(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $sprint = Sprint::factory()->create(['project_id' => $project->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/sprints/{$sprint->id}/burndown")->assertNotFound();
    }

    public function test_guest_cannot_see_the_burndown(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $sprint = Sprint::factory()->create(['project_id' => $project->id]);

        $this->getJson("/api/sprints/{$sprint->id}/burndown")->assertUnauthorized();
    }
}
