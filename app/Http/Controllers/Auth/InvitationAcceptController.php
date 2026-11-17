<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Members\AcceptInvitation;
use App\Enums\InvitationStatus;
use App\Exceptions\InvitationUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\MemberInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public page behind an invitation link. It reveals nothing about an
 * invitation that cannot be used: no email, no inviter.
 */
class InvitationAcceptController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = MemberInvitation::findByToken($token);
        $usable = $invitation !== null && $invitation->status() === InvitationStatus::Pending;

        return Inertia::render('auth/invitation', [
            'token' => $token,
            'state' => $invitation?->status()->value ?? 'invalid',
            'email' => $usable ? $invitation->email : null,
            'expiresAt' => $usable ? $invitation->expires_at->toIso8601String() : null,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = MemberInvitation::findByToken($token);

        if ($invitation === null || $invitation->status() !== InvitationStatus::Pending) {
            return to_route('invitations.show', $token);
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages(['email' => __('Someone with this email already has an account.')]);
        }

        try {
            $member = $acceptInvitation->handle(
                $invitation,
                $request->details(),
                $request->string('password')->toString(),
                $request->nidFile(),
                $request->photo(),
            );
        } catch (InvitationUnavailableException) {
            return to_route('invitations.show', $token);
        }

        Auth::login($member->user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to the mess. Your account is ready.')]);

        return to_route('dashboard');
    }
}
