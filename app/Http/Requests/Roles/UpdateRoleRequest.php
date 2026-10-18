<?php

namespace App\Http\Requests\Roles;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        $role = $this->role();
        $checks = [fn () => Gate::inspect('update', $role)];

        if ($this->filled('name') && $this->input('name') !== $role->name) {
            $checks[] = fn () => Gate::inspect('rename', $role);
        }

        if ($this->has('permissions')) {
            $checks[] = fn () => Gate::inspect('syncPermissions', [$role, (array) $this->input('permissions', [])]);
        }

        foreach ($checks as $check) {
            $response = $check();

            if ($response->denied()) {
                return $response;
            }
        }

        return Response::allow();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Role::class, 'name')->ignore($this->role()->id)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permission::values())],
        ];
    }

    /**
     * The validated permission names, or null when they were not submitted.
     *
     * @return array<int, string>|null
     */
    public function permissionNames(): ?array
    {
        return $this->has('permissions')
            ? $this->collect('permissions')->map(fn (mixed $name): string => (string) $name)->values()->all()
            : null;
    }

    private function role(): Role
    {
        /** @var Role */
        return $this->route('role');
    }
}
