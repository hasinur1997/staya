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
     * Delete a member. With no financial records they are hard-deleted with
     * their stays and files. With financial records they are soft-deleted
     * instead (hidden, history and files kept, NID number freed) and their
     * account is deactivated. The account of a hard-deleted member goes too
     * when it holds nothing but the Member role and nothing else points at
     * it; otherwise it is deactivated, and a staff account (other roles) is kept.
     *
     * @return 'deleted'|'deactivated'|'kept' What happened to the account
     */
    public function handle(User $actor, Member $member): string
    {
        [$files, $outcome] = DB::transaction(function () use ($actor, $member): array {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            Gate::forUser($actor)->authorize('delete', $member);

            $snapshot = $member->auditSnapshot();
            $user = $member->user;
            $soft = $member->hasFinancialRecords();
            $files = $soft ? [] : [$member->nid_file_path, $member->photo_path];

            if ($soft) {
                $member->forceFill(['nid_number_hash' => hash('sha256', 'deleted-member-'.$member->id)])->saveQuietly();
                $member->delete();
            } else {
                DB::table('member_invitations')->where('member_id', $member->id)->update(['member_id' => null]);
                $member->forceDelete();
            }

            $outcome = match (true) {
                ! $this->holdsOnlyTheMemberRole($user) => 'kept',
                $soft || $this->hasOtherHistory($user) => 'deactivated',
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
                ->withProperties(['account' => $outcome, 'user_id' => $user->id, 'soft' => $soft])
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
            || DB::table('grocery_purchases')->where('created_by', $user->id)->orWhere('updated_by', $user->id)->orWhere('voided_by', $user->id)->exists()
            || DB::table('month_closures')->where('closed_by', $user->id)->orWhere('reopened_by', $user->id)->exists()
            || DB::table('mess_settings')->where('updated_by', $user->id)->exists()
            || DB::table('mail_settings')->where('updated_by', $user->id)->exists()
            || DB::table('member_invitations')->where('invited_by', $user->id)->exists()
            || DB::table('meals')->where('recorded_by', $user->id)->exists()
            || DB::table('deposits')->where('submitted_by', $user->id)->orWhere('reviewed_by', $user->id)->orWhere('reversed_by', $user->id)->exists();
    }
}
