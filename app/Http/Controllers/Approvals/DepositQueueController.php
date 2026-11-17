<?php

namespace App\Http\Controllers\Approvals;

use App\Concerns\PresentsDeposits;
use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepositQueueController extends Controller
{
    use PresentsDeposits;

    /**
     * Pending deposits waiting for a decision, oldest first.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('review', Deposit::class);

        $actor = $request->user();

        $deposits = Deposit::query()
            ->with('member:id,first_name,last_name,name_bn,user_id')
            ->where('status', DepositStatus::Pending)
            ->orderBy('deposit_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Deposit $deposit) => [
                ...$this->depositRow($deposit, $actor),
                'can_approve' => $actor->can('approve', $deposit),
            ]);

        return Inertia::render('approvals/deposits/index', [
            'deposits' => $deposits,
        ]);
    }
}
