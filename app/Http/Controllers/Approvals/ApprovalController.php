<?php

namespace App\Http\Controllers\Approvals;

use App\Enums\UpcomingFeature;
use App\Http\Controllers\Controller;
use App\Http\Controllers\UpcomingFeatureController;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ApprovalController extends Controller
{
    /**
     * The approvals entry point. Registrations are the only queue so far;
     * reviewers of deposits alone still see the "coming soon" page until
     * the deposit queue ships.
     */
    public function __invoke(Request $request, UpcomingFeatureController $upcoming): RedirectResponse|Response
    {
        if ($request->user()->can('viewAnyRegistrations', Member::class)) {
            return to_route('approvals.registrations.index');
        }

        return $upcoming(UpcomingFeature::Approvals);
    }
}
