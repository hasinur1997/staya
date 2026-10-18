<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeactivateMember
{
    public function __construct(private CloseStay $closeStay) {}

    /**
     * The member left: close the open stay and mark them Inactive. Their
     * account can still sign in, read-only.
     */
    public function handle(User $actor, Member $member, string $leftOn): Member
    {
        return DB::transaction(function () use ($actor, $member, $leftOn): Member {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            Gate::forUser($actor)->authorize('deactivate', $member);

            $before = $member->auditSnapshot();

            $this->closeStay->handle($member, $leftOn);

            $member->status = MemberStatus::Inactive;
            $member->save();

            activity()
                ->useLog(AuditLog::Member)
                ->performedOn($member)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $member->auditSnapshot()])
                ->log('member_deactivated');

            return $member;
        });
    }
}
