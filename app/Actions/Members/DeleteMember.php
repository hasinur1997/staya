<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Enums\SystemRole;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DeleteMember
{
    /**
     * Hard-delete a member with no financial records, with their stays and
     * files. The account goes too when it holds nothing but the Member role
     * and nothing else points at it; an account with other history is
     * deactivated instead, and a staff account (other roles) is kept.
     *
     * @return 'deleted'|'deactivated'|'kept' What happened to the account
     */
    public function handle(User $actor, Member $member): string
    {
        [$files, $outcome] = DB::transaction(function () use ($actor, $member): array {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            Gate::forUser($actor)->authorize('delete', $member);

            $snapshot = $member->auditSnapshot();
            $files = [$member->nid_file_path, $member->photo_path];
            $user = $member->user;

            $member->delete();

            $outcome = match (true) {
                ! $this->holdsOnlyTheMemberRole($user) => 'kept',
                $this->hasOtherHistory($user) => 'deactivated',
                default => 'deleted',
            };

            if ($outcome === 'deactivated') {
                $user->forceFill(['is_active' => false, 'remember_token' => Str::random(60)])->save();
            }

            activity()
                ->useLog(AuditLog::Member)
                ->performedOn($member)
                ->event('deleted')
                ->withChanges(['old' => $snapshot])
                ->withProperties(['account' => $outcome, 'user_id' => $user->id])
                ->log('member_deleted');

            if ($outcome === 'deleted') {
                $user->delete();
            }

            return [$files, $outcome];
        });

        MemberUploads::delete(...$files);

        return $outcome;
    }

    private function holdsOnlyTheMemberRole(User $user): bool
    {
        return ! $user->roles()->where('name', '!=', SystemRole::Member->value)->exists();
    }

    /**
     * Whether any record names the user as its actor, so deleting the account
     * would break that record (the foreign keys RESTRICT it anyway).
     */
    private function hasOtherHistory(User $user): bool
    {
        return DB::table('ledger_entries')->where('created_by', $user->id)->exists()
            || DB::table('members')->where('reviewed_by', $user->id)->exists()
            || DB::table('month_closures')->where('closed_by', $user->id)->orWhere('reopened_by', $user->id)->exists()
            || DB::table('mess_settings')->where('updated_by', $user->id)->exists();
    }
}
