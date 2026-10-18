<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    /**
     * Determine whether the user can view roles.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::RoleView->value);
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $actor): bool
    {
        return $actor->can(Permission::RoleManage->value);
    }

    /**
     * Determine whether the user can edit the role.
     */
    public function update(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RoleManage->value);
    }

    /**
     * Determine whether the user can rename the role.
     */
    public function rename(User $actor, Role $role): Response
    {
        if (! $actor->can(Permission::RoleManage->value)) {
            return Response::deny();
        }

        return $role->is_system
            ? Response::deny(__('System roles cannot be renamed.'))
            : Response::allow();
    }

    /**
     * Determine whether the user can give the role exactly these permissions.
     * `$role` is a new, unsaved Role when creating one.
     *
     * @param  array<int, mixed>  $permissions
     */
    public function syncPermissions(User $actor, Role $role, array $permissions): Response
    {
        if (! $actor->can(Permission::RoleManage->value)) {
            return Response::deny();
        }

        $current = $role->exists ? $role->permissions()->pluck('name') : collect();
        $wanted = collect($permissions)->filter(fn ($name) => is_string($name))->unique();

        if ($wanted->diff($current)->isEmpty() && $current->diff($wanted)->isEmpty()) {
            return Response::allow();
        }

        if ($role->isAdmin()) {
            return Response::deny(__('The Admin role always holds every permission.'));
        }

        if ($role->exists && $actor->hasRole($role)) {
            return Response::deny(__('You cannot change the permissions of a role you hold.'));
        }

        if (! $actor->holdsAllPermissions($wanted->diff($current))) {
            return Response::deny(__('You cannot grant a permission you do not hold.'));
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete the role.
     */
    public function delete(User $actor, Role $role): Response
    {
        if (! $actor->can(Permission::RoleManage->value)) {
            return Response::deny();
        }

        if ($role->is_system) {
            return Response::deny(__('System roles cannot be deleted.'));
        }

        if ($role->users()->exists()) {
            return Response::deny(__('Move every user off this role before deleting it.'));
        }

        return Response::allow();
    }
}
