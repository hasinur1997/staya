<?php

namespace App\Policies;

use App\Enums\MemberStatus;
use App\Enums\Permission;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class MemberPolicy
{
    /**
     * Determine whether the user can view the member list.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::MemberView->value);
    }

    /**
     * Determine whether the user can view a member's profile. Everyone may
     * see their own.
     */
    public function view(User $actor, Member $member): bool
    {
        return $actor->can(Permission::MemberView->value) || $member->user_id === $actor->id;
    }

    /**
     * Determine whether the user can register a member. The new member gets
     * the Member role, so the actor must be able to grant it.
     */
    public function create(User $actor): Response
    {
        if (! $actor->can(Permission::MemberCreate->value)) {
            return Response::deny();
        }

        return $this->canGrantMemberRole($actor, new User);
    }

    /**
     * Determine whether the user can edit a member's details.
     */
    public function update(User $actor, Member $member): bool
    {
        return $actor->can(Permission::MemberUpdate->value);
    }

    /**
     * Determine whether the user can change the member's login email. That
     * could hand the account to someone else through a password reset, so it
     * needs the same rights as editing the account itself.
     */
    public function changeEmail(User $actor, Member $member): Response
    {
        if (! $actor->can(Permission::MemberUpdate->value)) {
            return Response::deny();
        }

        return Gate::forUser($actor)->inspect('update', $member->user);
    }

    /**
     * Determine whether the user can mark an active member as having left.
     */
    public function deactivate(User $actor, Member $member): bool
    {
        return $actor->can(Permission::MemberDeactivate->value) && $member->status === MemberStatus::Active;
    }

    /**
     * Determine whether the user can record an inactive member rejoining.
     */
    public function reactivate(User $actor, Member $member): bool
    {
        return $actor->can(Permission::MemberDeactivate->value) && $member->status === MemberStatus::Inactive;
    }

    /**
     * Determine whether the user can hard-delete the member. Only members
     * without financial records can go; everyone else is deactivated.
     */
    public function delete(User $actor, Member $member): Response
    {
        if (! $actor->can(Permission::MemberDelete->value)) {
            return Response::deny();
        }

        if ($member->user_id === $actor->id) {
            return Response::deny(__('You cannot delete your own member profile.'));
        }

        if ($member->hasFinancialRecords()) {
            return Response::deny(__('This member has financial records, so they can only be deactivated.'));
        }

        return $actor->holdsAllPermissions($member->user->getAllPermissions()->pluck('name'))
            ? Response::allow()
            : Response::deny(__('You cannot change a user who holds permissions you do not have.'));
    }

    /**
     * Determine whether the user can see NID numbers and files.
     */
    public function viewNid(User $actor, Member $member): bool
    {
        return $actor->can(Permission::MemberViewNid->value);
    }

    /**
     * Determine whether the user can open the registration queue.
     */
    public function viewAnyRegistrations(User $actor): bool
    {
        return $actor->can(Permission::MemberApproveRegistration->value);
    }

    /**
     * Determine whether the user can approve or reject this registration.
     * Approval grants the Member role, so the reviewer must be able to.
     */
    public function reviewRegistration(User $actor, Member $member): Response
    {
        if (! $actor->can(Permission::MemberApproveRegistration->value)) {
            return Response::deny();
        }

        if ($member->status !== MemberStatus::Pending) {
            return Response::deny(__('This registration has already been reviewed.'));
        }

        return $this->canGrantMemberRole($actor, $member->user);
    }

    /**
     * The same rules as assigning roles on the Users page: the actor must
     * hold every permission the role carries, and never changes their own.
     */
    private function canGrantMemberRole(User $actor, User $target): Response
    {
        $current = $target->exists ? $target->roles()->pluck('id')->all() : [];

        return Gate::forUser($actor)->inspect('assignRoles', [$target, [...$current, Role::member()->id]]);
    }
}
