<?php

namespace App\Http\Requests;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
{
    private ?Project $project = null;

    public function project(): Project
    {
        return $this->project ??= $this->user()->projects()->findOrFail($this->route('project'));
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->project());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                Rule::exists('users', 'email'),
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($this->project()->members()->where('users.email', $value)->exists()) {
                        $fail('The user is already a member.');
                    }
                },
            ],
        ];
    }
}
