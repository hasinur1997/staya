<?php

namespace App\Http\Controllers\Roles;

use App\Actions\Roles\CreateRole;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\UpdateRole;
use App\Enums\Permission as PermissionKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Show the role list.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $actor = $request->user();

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'can' => [
                    'update' => $actor->can('update', $role),
                ],
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'can' => [
                'create' => $actor->can('create', Role::class),
            ],
        ]);
    }

    /**
     * Show the form for a new role.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('roles/create', [
            'groups' => $this->permissionGroups($request->user()),
        ]);
    }

    /**
     * Store a new role.
     */
    public function store(StoreRoleRequest $request, CreateRole $createRole): RedirectResponse
    {
        $role = $createRole->handle($request->string('name')->toString(), $request->permissionNames());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.edit', $role);
    }

    /**
     * Show the form for editing a role.
     */
    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize('update', $role);

        $actor = $request->user();

        return Inertia::render('roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->is_system,
                'users_count' => $role->users()->count(),
                'permissions' => $role->permissions()->pluck('name'),
            ],
            'groups' => $this->permissionGroups($actor),
            'can' => [
                'rename' => $actor->can('rename', $role),
                // Unchanged permissions always pass, so ask with a change.
                'editPermissions' => ! $role->isAdmin() && ! $actor->hasRole($role),
                'delete' => $actor->can('delete', $role),
            ],
            'lockReason' => match (true) {
                $role->isAdmin() => __('The Admin role always holds every permission.'),
                $actor->hasRole($role) => __('You cannot change the permissions of a role you hold.'),
                default => null,
            },
        ]);
    }

    /**
     * Update a role.
     */
    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $updateRole): RedirectResponse
    {
        $updateRole->handle($role, $request->string('name')->toString(), $request->permissionNames());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return to_route('roles.edit', $role);
    }

    /**
     * Delete a role.
     */
    public function destroy(Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $deleteRole->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('roles.index');
    }

    /**
     * The catalog grouped by resource, marking what the actor may grant.
     *
     * @return array<int, array{group: string, label: string, permissions: array<int, array{name: string, description: string|null, grantable: bool}>}>
     */
    private function permissionGroups(User $actor): array
    {
        $held = $actor->getAllPermissions()->pluck('name');
        $order = array_flip(PermissionKey::values());

        return Permission::query()
            ->get()
            ->sortBy(fn (Permission $permission) => $order[$permission->name] ?? PHP_INT_MAX)
            ->groupBy('group')
            ->map(fn ($permissions, string $group) => [
                'group' => $group,
                'label' => PermissionKey::groupLabel($group),
                'permissions' => $permissions->map(fn (Permission $permission) => [
                    'name' => $permission->name,
                    'description' => $permission->description,
                    'grantable' => $held->contains($permission->name),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
