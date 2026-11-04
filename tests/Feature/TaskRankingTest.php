<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskRanking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FlushesRedis;
use Tests\TestCase;

class TaskRankingTest extends TestCase
{
    use FlushesRedis, RefreshDatabase;

    public function test_adding_a_done_task_gives_points_to_the_assignee(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $task = Task::factory()->done()->create([
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'points' => 5,
        ]);

        $ranking = app(TaskRanking::class);
        $ranking->add($task);

        $this->assertSame(
            [['user_id' => $user->id, 'points' => 5]],
            $ranking->top($project->id, now()),
        );
    }

    public function test_task_without_assignee_gives_no_points(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->done()->create([
            'project_id' => $project->id,
        ]);

        $ranking = app(TaskRanking::class);
        $ranking->add($task);

        $this->assertSame(
            [],
            $ranking->top($project->id, now()),
        );
    }

    public function test_removing_a_task_takes_its_points_back(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $task = Task::factory()->done()->create([
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'points' => 5,
        ]);

        $ranking = app(TaskRanking::class);
        $ranking->add($task);
        $ranking->remove($task);

        $this->assertSame([], $ranking->top($project->id, now()));
    }

    public function test_points_from_several_tasks_are_summed(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $task1 = Task::factory()->done()->create([
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'points' => 5,
        ]);

        $task2 = Task::factory()->done()->create([
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'points' => 3,
        ]);

        $ranking = app(TaskRanking::class);
        $ranking->add($task1);
        $ranking->add($task2);

        $this->assertSame(
            [['user_id' => $user->id, 'points' => 8]],
            $ranking->top($project->id, now()),
        );
    }

    public function test_points_are_counted_in_the_week_the_task_was_completed(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $thisWeek = Task::factory()->done()->create([
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'points' => 5,
        ]);

        $twoWeeksAgo = Task::factory()->done()->create([
            'project_id' => $project->id,
            'assignee_id' => $user->id,
            'points' => 3,
            'completed_at' => now()->subWeeks(2),
        ]);

        $ranking = app(TaskRanking::class);
        $ranking->add($thisWeek);
        $ranking->add($twoWeeksAgo);

        $this->assertSame(
            [['user_id' => $user->id, 'points' => 5]],
            $ranking->top($project->id, now()),
        );

        $this->assertSame(
            [['user_id' => $user->id, 'points' => 3]],
            $ranking->top($project->id, now()->subWeeks(2)),
        );
    }

    public function test_rankings_of_different_projects_do_not_mix(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $user = User::factory()->create();
        $task = Task::factory()->done()->create([
            'project_id' => $otherProject->id,
            'assignee_id' => $user->id,
            'points' => 5,
        ]);

        $ranking = app(TaskRanking::class);
        $ranking->add($task);

        $this->assertSame([], $ranking->top($project->id, now()));
        $this->assertSame(
            [['user_id' => $user->id, 'points' => 5]],
            $ranking->top($otherProject->id, now()),
        );
    }
}
