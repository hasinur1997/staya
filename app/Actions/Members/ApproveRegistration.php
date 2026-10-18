<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApproveRegistration
{
    public function __construct(private OpenStay $openStay) {}

    /**
     * Approve a pending registration: status Active, Member role assigned,
     * first stay opened. The row lock and status re-check make a repeated
     * or parallel approval fail instead of applying twice.
     */
    public function handle(User $actor, Member $member, string $joinedOn): Member
    {
        return DB::transaction(function () use ($actor, $member, $joinedOn): Member {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            Gate::forUser($actor)->authorize('reviewRegistration', $member);

            $before = $member->auditSnapshot();

            $member->status = MemberStatus::Active;
            $member->rejection_reason = null;
            $member->reviewed_by = $actor->id;
            $member->reviewed_at = now();
            $member->save();

            $member->user->assignRole(Role::member());

            $this->openStay->handle($member, $joinedOn);

            activity()
                ->useLog(AuditLog::Member)
                ->performedOn($member)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $member->auditSnapshot()])
                ->log('registration_approved');

            return $member;
        });
    }
}
