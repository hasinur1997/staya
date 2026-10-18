<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $create = Gate::inspect('create', User::class);

        return $create->denied() ? $create : Gate::inspect('assignRoles', [new User, (array) $this->input('roles', [])]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', 'distinct', Rule::exists(Role::class, 'id')],
        ];
    }

    /**
     * The validated role ids.
     *
     * @return array<int, int>
     */
    public function roleIds(): array
    {
        return $this->collect('roles')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
