<?php

namespace App\Actions\Members;

use App\Concerns\BusinessCalendar;
use App\Enums\AuditLog;
use App\Enums\InvitationStatus;
use App\Exceptions\InvitationUnavailableException;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class AcceptInvitation
{
    public function __construct(private CreateMember $createMember) {}

    /**
     * Turn an invitation into an approved member. The invitation row is
     * locked and re-checked, so a link used twice at the same moment creates
     * only one member. The inviter counts as the approver, the email is
     * already proven by the link, and the stay starts today.
     *
     * @param  array<string, string>  $details  Validated member fields, phone normalized
     *
     * @throws InvitationUnavailableException
     */
    public function handle(MemberInvitation $invitation, array $details, string $password, UploadedFile $nidFile, ?UploadedFile $photo = null): Member
    {
        return DB::transaction(function () use ($invitation, $details, $password, $nidFile, $photo): Member {
            $locked = MemberInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            if ($locked->status() !== InvitationStatus::Pending) {
                throw new InvitationUnavailableException($locked->status());
            }

            $member = $this->createMember->handle(
                User::query()->findOrFail($locked->invited_by),
                $details,
                ['email' => $locked->email, 'password' => $password, 'verified' => true],
                BusinessCalendar::today()->toDateString(),
                $nidFile,
                $photo,
            );

            $before = $locked->auditSnapshot();
            $locked->forceFill(['used_at' => now(), 'member_id' => $member->id])->save();

            activity()
                ->useLog(AuditLog::Member)
                ->causedBy($member->user)
                ->performedOn($locked)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => [...$locked->auditSnapshot(), 'member_id' => $member->id]])
                ->log('invitation_accepted');

            return $member;
        });
    }
}
