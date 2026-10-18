<?php

namespace App\Http\Controllers\Ledger;

use App\Actions\Ledger\PostAdjustment;
use App\Concerns\BusinessCalendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\StoreAdjustmentRequest;
use App\Models\LedgerEntry;
use App\Models\MessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdjustmentController extends Controller
{
    /**
     * Show the manual adjustment form.
     */
    public function create(): Response
    {
        Gate::authorize('adjust', LedgerEntry::class);

        return Inertia::render('ledger/adjustments/create', [
            'today' => BusinessCalendar::today()->toDateString(),
            'fundStartDate' => MessSetting::current()->fund_start_date?->toDateString(),
        ]);
    }

    /**
     * Post a manual adjustment.
     */
    public function store(StoreAdjustmentRequest $request, PostAdjustment $postAdjustment): RedirectResponse
    {
        $postAdjustment->handle($request->user(), $request->adjustment());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Adjustment posted.')]);

        return $this->afterPosting($request);
    }

    private function afterPosting(Request $request): RedirectResponse
    {
        return $request->user()->can('viewAny', LedgerEntry::class)
            ? to_route('ledger.index')
            : to_route('ledger.adjustments.create');
    }
}
