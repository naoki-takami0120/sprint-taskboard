<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'name' => 'Admin']);
        $members = User::factory()->count(3)->create();
        $project = Project::factory()->create(['owner_id' => $admin->id]);
        $project->members()->attach($admin->id);

        $memberIds = $members->pluck('id');
        $project->members()->attach($memberIds);

        $sprint = Sprint::factory()->create(['project_id' => $project->id]);

        $taskData = ['project_id' => $project->id, 'sprint_id' => $sprint->id];
        Task::factory()->count(4)->create($taskData);
        Task::factory()->count(3)->inProgress()->create($taskData);
        Task::factory()->count(3)->done()->create($taskData)->each(function ($task) use ($memberIds) {
            $task->update(['assignee_id' => $memberIds->random()]);
        });

    }
}
