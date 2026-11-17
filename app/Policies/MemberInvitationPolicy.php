<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Inviting someone is registering a member in advance, so it needs
 * `member.create` and the right to grant the Member role.
 */
class MemberInvitationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::MemberCreate->value);
    }

    public function create(User $actor): Response
    {
        return Gate::forUser($actor)->inspect('create', Member::class);
    }

    public function revoke(User $actor, MemberInvitation $invitation): bool
    {
        return $actor->can(Permission::MemberCreate->value);
    }
}
