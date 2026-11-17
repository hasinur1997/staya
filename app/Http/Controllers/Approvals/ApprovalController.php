<?php

namespace App\Http\Controllers\Approvals;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    /**
     * The approvals entry point: the first queue the user may review.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $actor = $request->user();

        return match (true) {
            $actor->can('review', Deposit::class) => to_route('approvals.deposits.index'),
            $actor->can('viewAnyRegistrations', Member::class) => to_route('approvals.registrations.index'),
            default => abort(403),
        };
    }
}
