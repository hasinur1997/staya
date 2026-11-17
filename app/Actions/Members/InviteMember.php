<?php

namespace App\Actions\Members;

use App\Enums\AuditLog;
use App\Mail\MemberInvitationMail;
use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InviteMember
{
    public const int DEFAULT_DAYS = 7;

    /**
     * Invite someone by email. Older pending invitations to the same address
     * are cancelled, so only the newest link works. The email is sent after
     * the invitation is saved. Authorization happens in the form request.
     */
    public function handle(User $actor, string $email, int $expiresInDays = self::DEFAULT_DAYS): MemberInvitation
    {
        ['token' => $token, 'hash' => $hash] = MemberInvitation::newToken();

        $invitation = DB::transaction(function () use ($actor, $email, $expiresInDays, $hash): MemberInvitation {
            MemberInvitation::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->get()
                ->each(function (MemberInvitation $older): void {
                    $before = $older->auditSnapshot();
                    $older->forceFill(['revoked_at' => now()])->save();

                    activity()
                        ->useLog(AuditLog::Member)
                        ->performedOn($older)
                        ->event('updated')
                        ->withChanges(['old' => $before, 'attributes' => $older->auditSnapshot()])
                        ->log('invitation_revoked');
                });

            $invitation = new MemberInvitation(['email' => $email, 'token_hash' => $hash, 'expires_at' => now()->addDays($expiresInDays)]);
            $invitation->invited_by = $actor->id;
            $invitation->save();

            activity()
                ->useLog(AuditLog::Member)
                ->performedOn($invitation)
                ->event('created')
                ->withChanges(['attributes' => $invitation->auditSnapshot()])
                ->log('invitation_sent');

            return $invitation;
        });

        Mail::to($email)->send(new MemberInvitationMail($invitation, route('invitations.show', $token), $actor->name));

        return $invitation;
    }
}
