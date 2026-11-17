<?php

namespace App\Http\Controllers\Members;

use App\Actions\Members\InviteMember;
use App\Actions\Members\RevokeInvitation;
use App\Enums\InvitationStatus;
use App\Exceptions\InvitationUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\StoreInvitationRequest;
use App\Models\Member;
use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff side of member invitations. Invitations are never edited or
 * deleted: they are cancelled, or replaced by sending a new one.
 */
class InvitationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MemberInvitation::class);

        $actor = $request->user();

        $invitations = MemberInvitation::query()
            ->with(['inviter:id,name', 'member:id,first_name,last_name,name_bn'])
            ->latest('id')
            ->paginate(25)
            ->through(function (MemberInvitation $invitation) use ($actor): array {
                $status = $invitation->status();

                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'invited_by' => $invitation->inviter->name,
                    'created_at' => $invitation->created_at?->toIso8601String(),
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                    'used_at' => $invitation->used_at?->toIso8601String(),
                    'member_id' => $invitation->member_id,
                    'member_name' => $invitation->member?->full_name,
                    'can' => [
                        'revoke' => $status === InvitationStatus::Pending && $actor->can('revoke', $invitation),
                        'resend' => $status !== InvitationStatus::Used && $actor->can('create', MemberInvitation::class),
                    ],
                ];
            });

        return Inertia::render('members/invitations/index', [
            'invitations' => $invitations,
            'defaultDays' => InviteMember::DEFAULT_DAYS,
            'can' => [
                'create' => $actor->can('create', MemberInvitation::class),
                'viewMembers' => $actor->can('viewAny', Member::class),
            ],
        ]);
    }

    public function store(StoreInvitationRequest $request, InviteMember $inviteMember): RedirectResponse
    {
        $inviteMember->handle($request->user(), $request->string('email')->lower()->toString(), $request->expiresInDays());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $request->string('email')->lower()->toString()])]);

        return back();
    }

    /**
     * Send a fresh link to the same address; the old one stops working.
     */
    public function resend(Request $request, MemberInvitation $invitation, InviteMember $inviteMember): RedirectResponse
    {
        Gate::authorize('create', MemberInvitation::class);

        if ($invitation->status() === InvitationStatus::Used) {
            throw ValidationException::withMessages(['invitation' => __('This invitation was already accepted.')]);
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages(['invitation' => __('Someone with this email already has an account.')]);
        }

        $inviteMember->handle($request->user(), $invitation->email);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('A new invitation was sent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    public function revoke(MemberInvitation $invitation, RevokeInvitation $revokeInvitation): RedirectResponse
    {
        Gate::authorize('revoke', $invitation);

        try {
            $revokeInvitation->handle($invitation);
        } catch (InvitationUnavailableException) {
            throw ValidationException::withMessages(['invitation' => __('Only a pending invitation can be cancelled.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return back();
    }
}
