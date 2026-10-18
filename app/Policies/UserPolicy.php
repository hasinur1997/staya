<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Collection;

class UserPolicy
{
    /**
     * Determine whether the user can view the user list.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UserView->value);
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $actor): bool
    {
        return $actor->can(Permission::UserCreate->value);
    }

    /**
     * Determine whether the user can edit the target's account. Nobody may
     * edit someone who holds more than they do: changing that person's email
     * would let the actor take the account over through a password reset.
     */
    public function update(User $actor, User $target): Response
    {
        if (! $actor->can(Permission::UserUpdate->value)) {
            return Response::deny();
        }

        return $this->outranks($actor, $target);
    }

    /**
     * Determine whether the user can give the target exactly these roles.
     * `$target` is a new, unsaved User when creating an account.
     *
     * @param  array<int, mixed>  $roleIds
     */
    public function assignRoles(User $actor, User $target, array $roleIds): Response
    {
        $current = $target->exists ? $target->roles()->pluck('id') : collect();
        $wanted = collect($roleIds)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique();
        $added = $wanted->diff($current);
        $removed = $current->diff($wanted);

        if ($added->isEmpty() && $removed->isEmpty()) {
            return Response::allow();
        }

        if ($target->exists && $actor->is($target)) {
            return Response::deny(__('You cannot change your own roles.'));
        }

        $changed = Role::query()->with('permissions')->whereKey($added->merge($removed))->get();

        if (! $actor->holdsAllPermissions($this->permissionNames($changed))) {
            return Response::deny(__('You can only assign or remove roles whose permissions you hold yourself.'));
        }

        if ($changed->contains(fn (Role $role) => $role->isAdmin() && $removed->contains($role->id))
            && $this->isLastActiveAdmin($target)) {
            return Response::deny(__('At least one active Admin must remain.'));
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can deactivate the target.
     */
    public function deactivate(User $actor, User $target): Response
    {
        if (! $actor->can(Permission::UserDeactivate->value) || ! $target->is_active) {
            return Response::deny();
        }

        if ($actor->is($target)) {
            return Response::deny(__('You cannot deactivate your own account.'));
        }

        if ($this->isLastActiveAdmin($target)) {
            return Response::deny(__('At least one active Admin must remain.'));
        }

        return $this->outranks($actor, $target);
    }

    /**
     * Determine whether the user can reactivate the target.
     */
    public function reactivate(User $actor, User $target): Response
    {
        if (! $actor->can(Permission::UserDeactivate->value) || $target->is_active) {
            return Response::deny();
        }

        return $this->outranks($actor, $target);
    }

    /**
     * The actor must hold every permission the target holds.
     */
    private function outranks(User $actor, User $target): Response
    {
        if ($actor->is($target)) {
            return Response::allow();
        }

        return $actor->holdsAllPermissions($target->getAllPermissions()->pluck('name'))
            ? Response::allow()
            : Response::deny(__('You cannot change a user who holds permissions you do not have.'));
    }

    private function isLastActiveAdmin(User $target): bool
    {
        if (! $target->exists || ! $target->is_active) {
            return false;
        }

        $admin = Role::admin();

        if (! $target->roles()->whereKey($admin->id)->exists()) {
            return false;
        }

        return $admin->users()->where('is_active', true)->count() <= 1;
    }

    /**
     * @param  Collection<int, Role>  $roles
     * @return Collection<int, string>
     */
    private function permissionNames(Collection $roles): Collection
    {
        return $roles->flatMap(fn (Role $role) => $role->permissions->pluck('name'))->unique()->values();
    }
}
