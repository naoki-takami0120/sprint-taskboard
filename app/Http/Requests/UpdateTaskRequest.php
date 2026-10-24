<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    private ?Task $task = null;

    public function task(): Task
    {
        return $this->task ??= Task::visibleTo($this->user())->findOrFail($this->route('task'));
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->task();

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sprint_id' => ['nullable', Rule::exists('sprints', 'id')->where('project_id', $this->task()->project_id)],
            'assignee_id' => ['nullable', Rule::exists('project_user', 'user_id')->where('project_id', $this->task()->project_id)],
            'due_date' => ['nullable', 'date'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
