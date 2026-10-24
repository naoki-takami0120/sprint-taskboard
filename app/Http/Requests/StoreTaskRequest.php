<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    private ?Project $project = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->project();

        return true;
    }

    public function project(): Project
    {
        return $this->project ??= $this->user()->projects()->findOrFail($this->route('project'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $projectId = $this->project()->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sprint_id' => ['nullable', Rule::exists('sprints', 'id')->where('project_id', $projectId)],
            'assignee_id' => ['nullable', Rule::exists('project_user', 'user_id')->where('project_id', $projectId)],
            'due_date' => ['nullable', 'date'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
