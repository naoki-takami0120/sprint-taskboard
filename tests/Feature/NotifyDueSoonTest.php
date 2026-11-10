<?php

namespace Tests\Feature;

use App\Jobs\SendDueSoonNotice;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotifyDueSoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_notice_for_a_task_due_tomorrow(): void
    {
        Queue::fake();

        $task = Task::factory()->create([
            'assignee_id' => User::factory()->create()->id,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->artisan('tasks:notify-due-soon')->assertSuccessful();

        Queue::assertPushed(SendDueSoonNotice::class, fn ($job) => $job->taskId === $task->id);
    }

    public function test_it_does_not_queue_a_notice_for_a_done_task(): void
    {
        Queue::fake();

        Task::factory()->done()->create([
            'assignee_id' => User::factory()->create()->id,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->artisan('tasks:notify-due-soon')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_it_does_not_queue_a_notice_for_a_task_without_an_assignee(): void
    {
        Queue::fake();

        Task::factory()->create([
            'assignee_id' => null,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->artisan('tasks:notify-due-soon')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_it_does_not_queue_a_notice_for_an_already_notified_task(): void
    {
        Queue::fake();

        Task::factory()->create([
            'assignee_id' => User::factory()->create()->id,
            'due_date' => now()->addDay()->toDateString(),
            'due_notified_at' => now(),
        ]);

        $this->artisan('tasks:notify-due-soon')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_it_ignores_tasks_that_are_not_due_soon(): void
    {
        Queue::fake();

        $assignee = User::factory()->create();

        Task::factory()->create([
            'assignee_id' => $assignee->id,
            'due_date' => now()->addDays(2)->toDateString(),
        ]);
        Task::factory()->create([
            'assignee_id' => $assignee->id,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('tasks:notify-due-soon')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
