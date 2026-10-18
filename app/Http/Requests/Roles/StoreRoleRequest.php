<?php

namespace App\Http\Requests\Roles;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $create = Gate::inspect('create', Role::class);

        return $create->denied() ? $create : Gate::inspect('syncPermissions', [new Role, (array) $this->input('permissions', [])]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Role::class, 'name')],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::values())],
        ];
    }

    /**
     * The validated permission names.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return $this->collect('permissions')->map(fn (mixed $name): string => (string) $name)->values()->all();
    }
}
