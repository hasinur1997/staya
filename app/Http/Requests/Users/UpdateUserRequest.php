<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $target = $this->target();
        $update = Gate::inspect('update', $target);

        if ($update->denied() || ! $this->has('roles')) {
            return $update;
        }

        return Gate::inspect('assignRoles', [$target, (array) $this->input('roles', [])]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->target()->id),
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['integer', 'distinct', Rule::exists(Role::class, 'id')],
        ];
    }

    /**
     * The validated role ids, or null when roles were not submitted.
     *
     * @return array<int, int>|null
     */
    public function roleIds(): ?array
    {
        return $this->has('roles')
            ? $this->collect('roles')->map(fn (mixed $id): int => (int) $id)->values()->all()
            : null;
    }

    private function target(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
