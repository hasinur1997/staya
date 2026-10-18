<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationStatusController extends Controller
{
    /**
     * Where an applicant lands until their registration is approved: still
     * waiting, or rejected with the reason.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        if (! $user->isApplicant()) {
            return to_route('dashboard');
        }

        /** @var Member $member */
        $member = $user->member;

        return Inertia::render('auth/registration-status', [
            'status' => $member->status->value,
            'reason' => $member->rejection_reason,
            'emailVerified' => $user->hasVerifiedEmail(),
        ]);
    }
}
