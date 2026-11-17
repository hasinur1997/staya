<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Enums\InvitationStatus;
use App\Exceptions\InvitationUnavailableException;
use App\Models\MemberInvitation;
use Illuminate\Support\Facades\DB;

class RevokeInvitation
{
    /**
     * Cancel a pending invitation so its link stops working.
     *
     * @throws InvitationUnavailableException when it was already accepted, cancelled or expired
     */
    public function handle(MemberInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $locked = MemberInvitation::query()->lockForUpdate()->findOrFail($invitation->id);

            if ($locked->status() !== InvitationStatus::Pending) {
                throw new InvitationUnavailableException($locked->status());
            }

            $before = $locked->auditSnapshot();
            $locked->forceFill(['revoked_at' => now()])->save();

            activity()
                ->useLog(AuditLog::Member)
                ->performedOn($locked)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $locked->auditSnapshot()])
                ->log('invitation_revoked');
        });
    }
}
