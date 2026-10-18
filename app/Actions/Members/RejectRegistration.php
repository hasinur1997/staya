<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RejectRegistration
{
    /**
     * Reject a pending registration. The applicant sees the reason when they
     * sign in and still has no access.
     */
    public function handle(User $actor, Member $member, string $reason): Member
    {
        return DB::transaction(function () use ($actor, $member, $reason): Member {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            Gate::forUser($actor)->authorize('reviewRegistration', $member);

            $before = $member->auditSnapshot();

            $member->status = MemberStatus::Rejected;
            $member->rejection_reason = $reason;
            $member->reviewed_by = $actor->id;
            $member->reviewed_at = now();
            $member->save();

            activity()
                ->useLog(AuditLog::Member)
                ->performedOn($member)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $member->auditSnapshot()])
                ->log('registration_rejected');

            return $member;
        });
    }
}
