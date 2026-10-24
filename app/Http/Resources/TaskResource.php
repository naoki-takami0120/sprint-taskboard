<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'sprint_id' => $this->sprint_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'assignee_id' => $this->assignee_id,
            'due_date' => $this->due_date?->toDateString(),
            'points' => $this->points,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
