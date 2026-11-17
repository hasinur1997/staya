<?php

namespace App\Policies;

use App\Enums\DepositStatus;
use App\Enums\Permission;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DepositPolicy
{
    /**
     * Determine whether the user can open the deposits list (their own, or
     * everyone's with `deposit.view_all`).
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::DepositViewOwn->value) || $actor->can(Permission::DepositViewAll->value);
    }

    /**
     * Determine whether the user can see every member's deposits.
     */
    public function viewAll(User $actor): bool
    {
        return $actor->can(Permission::DepositViewAll->value);
    }

    /**
     * Determine whether the user can view a deposit.
     */
    public function view(User $actor, Deposit $deposit): bool
    {
        return $actor->can(Permission::DepositViewAll->value)
            || ($actor->can(Permission::DepositViewOwn->value) && $deposit->belongsToUser($actor));
    }

    /**
     * Determine whether the user can submit a deposit of their own. Only an
     * active member may; inactive members are read-only.
     */
    public function create(User $actor): Response
    {
        if (! $actor->can(Permission::DepositCreateOwn->value)) {
            return Response::deny();
        }

        return $actor->isActiveMember()
            ? Response::allow()
            : Response::deny(__('Only active members can submit deposits.'));
    }

    /**
     * Determine whether the user can record a deposit for this member: their
     * own through `create`, anyone else's with `deposit.create_for_others`.
     */
    public function createFor(User $actor, Member $member): Response
    {
        if ($member->user_id === $actor->id) {
            return $this->create($actor);
        }

        return $actor->can(Permission::DepositCreateForOthers->value) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can record deposits for other members.
     */
    public function createForOthers(User $actor): bool
    {
        return $actor->can(Permission::DepositCreateForOthers->value);
    }

    /**
     * Determine whether the user may approve a deposit for this member as
     * they record it. The self-approval rule applies.
     */
    public function approveOnSave(User $actor, Member $member): Response
    {
        if (! $actor->can(Permission::DepositApprove->value)) {
            return Response::deny();
        }

        return $this->selfApprovalRule($actor, $member->user_id === $actor->id);
    }

    /**
     * Determine whether the user can cancel the deposit: their own, still
     * pending.
     */
    public function cancel(User $actor, Deposit $deposit): bool
    {
        return $actor->can(Permission::DepositCancelOwn->value)
            && $deposit->status === DepositStatus::Pending
            && $deposit->belongsToUser($actor);
    }

    /**
     * Determine whether the user can open the deposit review queue.
     */
    public function review(User $actor): bool
    {
        return $actor->can(Permission::DepositApprove->value);
    }

    /**
     * Determine whether the user can approve the deposit. Nobody approves
     * their own without `deposit.approve_own`.
     */
    public function approve(User $actor, Deposit $deposit): Response
    {
        if (! $actor->can(Permission::DepositApprove->value)) {
            return Response::deny();
        }

        if ($deposit->status !== DepositStatus::Pending) {
            return Response::deny(__('This deposit has already been reviewed.'));
        }

        return $this->selfApprovalRule($actor, $deposit->belongsToUser($actor));
    }

    /**
     * Determine whether the user can reject the deposit.
     */
    public function reject(User $actor, Deposit $deposit): Response
    {
        if (! $actor->can(Permission::DepositApprove->value)) {
            return Response::deny();
        }

        return $deposit->status === DepositStatus::Pending
            ? Response::allow()
            : Response::deny(__('This deposit has already been reviewed.'));
    }

    /**
     * Determine whether the user can reverse an approved deposit.
     */
    public function reverse(User $actor, Deposit $deposit): Response
    {
        if (! $actor->can(Permission::DepositReverse->value)) {
            return Response::deny();
        }

        return $deposit->status === DepositStatus::Approved
            ? Response::allow()
            : Response::deny(__('Only approved deposits can be reversed.'));
    }

    private function selfApprovalRule(User $actor, bool $isOwn): Response
    {
        if ($isOwn && ! $actor->can(Permission::DepositApproveOwn->value)) {
            return Response::deny(__('You cannot approve your own deposit.'));
        }

        return Response::allow();
    }
}
