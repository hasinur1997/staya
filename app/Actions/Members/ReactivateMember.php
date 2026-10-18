<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReactivateMember
{
    public function __construct(private OpenStay $openStay) {}

    /**
     * The member rejoined: open a new stay and mark them Active.
     */
    public function handle(User $actor, Member $member, string $joinedOn): Member
    {
        return DB::transaction(function () use ($actor, $member, $joinedOn): Member {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            Gate::forUser($actor)->authorize('reactivate', $member);

            $before = $member->auditSnapshot();

            $this->openStay->handle($member, $joinedOn);

            $member->status = MemberStatus::Active;
            $member->save();

            activity()
                ->useLog(AuditLog::Member)
                ->performedOn($member)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $member->auditSnapshot()])
                ->log('member_reactivated');

            return $member;
        });
    }
}
