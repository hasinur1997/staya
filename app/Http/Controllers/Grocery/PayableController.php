<?php

namespace App\Http\Controllers\Grocery;

use App\Actions\Grocery\ReimbursePayable;
use App\Concerns\Money;
use App\Concerns\PresentsPurchases;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grocery\ReimbursePayableRequest;
use App\Models\BuyerPayable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayableController extends Controller
{
    use PresentsPurchases;

    /**
     * Show what the fund still owes buyers, oldest purchase first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BuyerPayable::class);

        $actor = $request->user();

        $payables = BuyerPayable::query()
            ->open()
            ->with(['purchase', 'member:id,first_name,last_name'])
            ->get()
            ->sortBy(fn (BuyerPayable $payable) => [$payable->purchase->purchase_date->toDateString(), $payable->id])
            ->values();

        $outstanding = '0.00';

        foreach ($payables as $payable) {
            $outstanding = bcadd($outstanding, $payable->outstanding(), Money::SCALE);
        }

        return Inertia::render('payables/index', [
            'payables' => $payables->map(fn (BuyerPayable $payable) => $this->payableRow($payable, $actor)),
            'outstanding' => $outstanding,
        ]);
    }

    /**
     * Record cash handed to the buyer.
     */
    public function reimburse(ReimbursePayableRequest $request, BuyerPayable $payable, ReimbursePayable $reimbursePayable): RedirectResponse
    {
        $payable = $reimbursePayable->handle($request->user(), $payable, $request->string('amount')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => $payable->isOpen()
            ? __('Reimbursement recorded. :amount is still owed.', ['amount' => Money::format($payable->outstanding())])
            : __('Reimbursement recorded. The buyer is fully repaid.')]);

        return to_route('payables.index');
    }
}
