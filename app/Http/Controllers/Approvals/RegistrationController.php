<?php

namespace App\Http\Controllers\Approvals;

use App\Actions\Members\ApproveRegistration;
use App\Actions\Members\RejectRegistration;
use App\Concerns\BusinessCalendar;
use App\Concerns\PresentsMembers;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\RejectRegistrationRequest;
use App\Http\Requests\Members\StayDateRequest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    use PresentsMembers;

    /**
     * Show the queue of registrations waiting for a decision, oldest first.
     */
    public function index(): Response
    {
        Gate::authorize('viewAnyRegistrations', Member::class);

        $registrations = Member::query()
            ->with('user:id,email,email_verified_at')
            ->where('status', MemberStatus::Pending)
            ->oldest()
            ->get()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $member->full_name,
                'email' => $member->user->email,
                'email_verified' => $member->user->email_verified_at !== null,
                'phone' => $member->phone,
                'registered_at' => $member->created_at?->toIso8601String(),
            ]);

        return Inertia::render('approvals/registrations/index', [
            'registrations' => $registrations,
        ]);
    }

    /**
     * Show one registration for review.
     */
    public function show(Request $request, Member $member): Response
    {
        Gate::authorize('viewAnyRegistrations', Member::class);

        $actor = $request->user();

        return Inertia::render('approvals/registrations/show', [
            'member' => $this->memberProfile($member, $actor),
            ...$this->nidProp($member, $actor, 'registration_review'),
            'today' => BusinessCalendar::today()->toDateString(),
            'can' => [
                'review' => $actor->can('reviewRegistration', $member),
                'viewNid' => $actor->can('viewNid', $member),
            ],
        ]);
    }

    /**
     * Approve a registration.
     */
    public function approve(StayDateRequest $request, Member $member, ApproveRegistration $approveRegistration): RedirectResponse
    {
        $approveRegistration->handle($request->user(), $member, $request->stayDate());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now a member.', ['name' => $member->full_name])]);

        return to_route('approvals.registrations.index');
    }

    /**
     * Reject a registration with a reason.
     */
    public function reject(RejectRegistrationRequest $request, Member $member, RejectRegistration $rejectRegistration): RedirectResponse
    {
        $rejectRegistration->handle($request->user(), $member, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Registration rejected.')]);

        return to_route('approvals.registrations.index');
    }
}
