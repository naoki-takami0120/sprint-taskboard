<?php

namespace Tests\Feature;

use App\Jobs\SendDueSoonNotice;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDueSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendDueSoonNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_the_assignee_and_marks_the_task(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assignee_id' => $assignee->id,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        (new SendDueSoonNotice($task->id))->handle();

        Notification::assertSentTo($assignee, TaskDueSoon::class);
        $this->assertNotNull($task->refresh()->due_notified_at);
    }

    public function test_it_does_not_notify_when_the_task_is_already_done(): void
    {
        Notification::fake();

        $task = Task::factory()->done()->create([
            'assignee_id' => User::factory()->create()->id,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        (new SendDueSoonNotice($task->id))->handle();

        Notification::assertNothingSent();
        $this->assertNull($task->refresh()->due_notified_at);
    }

    public function test_running_the_job_twice_notifies_only_once(): void
    {
        Notification::fake();

        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assignee_id' => $assignee->id,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        (new SendDueSoonNotice($task->id))->handle();
        (new SendDueSoonNotice($task->id))->handle();

        Notification::assertSentToTimes($assignee, TaskDueSoon::class, 1);
    }

    public function test_changing_the_due_date_clears_the_notified_mark(): void
    {
        $task = Task::factory()->create([
            'assignee_id' => User::factory()->create()->id,
            'due_date' => now()->addDay()->toDateString(),
            'due_notified_at' => now(),
        ]);

        $task->update(['due_date' => now()->addDays(5)->toDateString()]);

        $this->assertNull($task->refresh()->due_notified_at);
    }
}
