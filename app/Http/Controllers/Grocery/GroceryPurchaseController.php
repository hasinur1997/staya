<?php

namespace App\Http\Controllers\Grocery;

use App\Actions\Grocery\ReceiptUploads;
use App\Actions\Grocery\RecordPurchase;
use App\Actions\Grocery\UpdatePurchase;
use App\Actions\Grocery\VoidPurchase;
use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Concerns\PresentsPurchases;
use App\Enums\GroceryUnit;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grocery\PurchaseRequest;
use App\Http\Requests\Grocery\VoidPurchaseRequest;
use App\Models\BuyerPayable;
use App\Models\GroceryPurchase;
use App\Models\Member;
use App\Models\MessSetting;
use App\Models\User;
use App\Policies\GroceryPurchasePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroceryPurchaseController extends Controller
{
    use PresentsPurchases;

    /**
     * Show every member's purchases, newest first. Voided ones are hidden.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', GroceryPurchase::class);

        $actor = $request->user();
        $filters = $this->filters($request);

        $query = GroceryPurchase::query()
            ->active()
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('purchase_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('purchase_date', '<=', $to))
            ->when($filters['buyer'], fn (Builder $query, int $buyer) => $query->where('buyer_member_id', $buyer));

        // Summed with bcmath: SQLite's SUM() over DECIMAL is not exact.
        $total = '0.00';

        foreach ((clone $query)->toBase()->pluck('grand_total') as $grandTotal) {
            $total = bcadd($total, Money::numeric((string) $grandTotal), Money::SCALE);
        }

        $purchases = $query
            ->with(['buyer:id,first_name,last_name,user_id', 'payer:id,first_name,last_name', 'items'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (GroceryPurchase $purchase) => $this->purchaseRow($purchase, $actor));

        return Inertia::render('grocery/index', [
            'purchases' => $purchases,
            'filters' => [
                'month' => $filters['month'],
                'from' => $filters['month'] ? null : $filters['from'],
                'to' => $filters['month'] ? null : $filters['to'],
                'buyer' => $filters['buyer'],
            ],
            'total' => $total,
            'buyers' => $this->buyerOptions(activeOnly: false),
            'serverNow' => now()->toIso8601String(),
            'can' => [
                'create' => $actor->can('create', GroceryPurchase::class) || $actor->can('createForOthers', GroceryPurchase::class),
            ],
        ]);
    }

    /**
     * Show the form for a new purchase.
     */
    public function create(Request $request): Response
    {
        $actor = $request->user();
        $forOthers = $actor->can('createForOthers', GroceryPurchase::class);

        if (! $forOthers) {
            Gate::authorize('create', GroceryPurchase::class);
        }

        return Inertia::render('grocery/create', [
            ...$this->formProps(),
            'buyers' => $forOthers ? $this->buyerOptions() : [],
            'payers' => $forOthers ? $this->buyerOptions() : [],
            'ownMemberId' => $actor->can('create', GroceryPurchase::class) ? $actor->member?->id : null,
            'can' => ['chooseBuyer' => $forOthers, 'choosePayer' => $forOthers],
        ]);
    }

    /**
     * Store a new purchase and pay for it from the fund.
     */
    public function store(PurchaseRequest $request, RecordPurchase $recordPurchase): RedirectResponse
    {
        /** @var Member $buyer Resolved and checked by the request. */
        $buyer = $request->buyer();

        $purchase = $recordPurchase->handle($request->user(), $buyer, $request->purchaseData(), $request->receipt());

        $payable = $purchase->payable()->first();

        Inertia::flash('toast', $payable instanceof BuyerPayable && $payable->isOpen()
            ? ['type' => $purchase->isPaidByMember() ? 'success' : 'warning', 'message' => $purchase->isPaidByMember()
                ? __('Purchase recorded. The fund owes :name :amount.', ['name' => $payable->member->full_name, 'amount' => Money::format($payable->outstanding())])
                : __('Purchase recorded. The fund could not cover :amount, which is now owed to the buyer.', ['amount' => Money::format($payable->outstanding())])]
            : ['type' => 'success', 'message' => __('Purchase recorded.')]);

        return to_route('grocery.show', $purchase);
    }

    /**
     * Show one purchase with its items. Voided purchases stay reachable
     * here, marked as voided.
     */
    public function show(Request $request, GroceryPurchase $purchase): Response
    {
        Gate::authorize('view', $purchase);

        $actor = $request->user();
        $purchase->load(['buyer:id,first_name,last_name,user_id', 'payer:id,first_name,last_name', 'items', 'payable.member:id,first_name,last_name', 'creator:id,name', 'updater:id,name', 'voider:id,name']);

        return Inertia::render('grocery/show', [
            'purchase' => $this->purchaseDetail($purchase, $actor),
            'serverNow' => now()->toIso8601String(),
            'can' => [
                'viewAny' => $actor->can('viewAny', GroceryPurchase::class),
                'viewBuyer' => $actor->can('view', $purchase->buyer),
                'viewPayables' => $actor->can('viewAny', BuyerPayable::class),
            ],
        ]);
    }

    /**
     * Show the form for editing a purchase.
     */
    public function edit(Request $request, GroceryPurchase $purchase): Response
    {
        Gate::authorize('update', $purchase);

        $actor = $request->user();
        $purchase->load(['buyer:id,first_name,last_name,user_id', 'payer:id,first_name,last_name', 'items', 'payable.member:id,first_name,last_name', 'creator:id,name', 'updater:id,name', 'voider:id,name']);
        $changeBuyer = $actor->can('createForOthers', GroceryPurchase::class);

        return Inertia::render('grocery/edit', [
            ...$this->formProps(),
            'purchase' => $this->purchaseDetail($purchase, $actor),
            'buyers' => $changeBuyer ? $this->buyerOptions(keep: $purchase->buyer) : [],
            'payers' => $changeBuyer ? $this->buyerOptions(keep: $purchase->payer) : [],
            'serverNow' => now()->toIso8601String(),
            'can' => ['chooseBuyer' => $changeBuyer, 'choosePayer' => $changeBuyer],
        ]);
    }

    /**
     * Update a purchase, reversing its old ledger amount and posting the
     * new one.
     */
    public function update(PurchaseRequest $request, GroceryPurchase $purchase, UpdatePurchase $updatePurchase): RedirectResponse
    {
        $updatePurchase->handle(
            $request->user(),
            $purchase,
            $request->purchaseData(),
            $request->filled('buyer_member_id') ? $request->buyer() : null,
            $request->receipt(),
            $request->boolean('remove_receipt'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase updated.')]);

        return to_route('grocery.show', $purchase);
    }

    /**
     * Void a purchase. Nothing is deleted: it is hidden from lists and its
     * ledger effect is reversed.
     */
    public function void(VoidPurchaseRequest $request, GroceryPurchase $purchase, VoidPurchase $voidPurchase): RedirectResponse
    {
        $voidPurchase->handle($request->user(), $purchase, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase deleted. Its ledger amount was reversed.')]);

        return to_route('grocery.index');
    }

    /**
     * Show the receipt, which lives on the private disk.
     */
    public function receipt(GroceryPurchase $purchase): StreamedResponse
    {
        Gate::authorize('view', $purchase);

        $disk = Storage::disk(ReceiptUploads::DISK);

        abort_unless($purchase->receipt_path !== null && $disk->exists($purchase->receipt_path), 404);

        return $disk->response($purchase->receipt_path, headers: ['Cache-Control' => 'private, max-age=3600']);
    }

    /**
     * The list filters: a month (YYYY-MM) wins over a custom date range.
     *
     * @return array{month: string|null, from: string|null, to: string|null, buyer: int|null}
     */
    private function filters(Request $request): array
    {
        $date = fn (string $key): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) === 1
            ? (string) $request->query($key)
            : null;

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month')) === 1 ? (string) $request->query('month') : null;
        [$from, $to] = $month !== null
            ? BusinessCalendar::monthDates((int) substr($month, 0, 4), (int) substr($month, 5, 2))
            : [$date('from'), $date('to')];

        return [
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'buyer' => $request->filled('buyer') ? $request->integer('buyer') : null,
        ];
    }

    /**
     * What the create and edit forms need besides the purchase.
     *
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'today' => BusinessCalendar::today()->toDateString(),
            'fundStartDate' => MessSetting::current()->fund_start_date?->toDateString(),
            'units' => GroceryUnit::options(),
        ];
    }

    /**
     * Members for the buyer and paid-by fields or the filter. A newly
     * chosen member must be active; `$keep` adds the one a purchase
     * already has even after they left.
     *
     * @return array<int, array{id: int, name: string, status: string}>
     */
    private function buyerOptions(bool $activeOnly = true, ?Member $keep = null): array
    {
        return Member::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('status', $activeOnly ? [MemberStatus::Active] : [MemberStatus::Active, MemberStatus::Inactive])
                ->when($keep, fn (Builder $query, Member $keep) => $query->orWhere('id', $keep->id)))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Member $member) => ['id' => $member->id, 'name' => $member->full_name, 'status' => $member->status->value])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseDetail(GroceryPurchase $purchase, User $viewer): array
    {
        $payable = $purchase->payable;

        return [
            ...$this->purchaseRow($purchase, $viewer),
            'items' => $this->purchaseItems($purchase),
            'notes' => $purchase->notes,
            'has_receipt' => $purchase->receipt_path !== null,
            'fund_paid' => $purchase->expenseEntry()->first()->amount ?? '0.00',
            'void_reason' => $purchase->void_reason,
            'void_needs_reason' => app(GroceryPurchasePolicy::class)->voidNeedsReason($viewer, $purchase),
            'created_by' => $purchase->creator->name,
            'created_at' => $purchase->created_at->toIso8601String(),
            'updated_by' => $purchase->updater?->name,
            'updated_at' => $purchase->updated_by !== null ? $purchase->updated_at?->toIso8601String() : null,
            'voided_by' => $purchase->voider?->name,
            'voided_at' => $purchase->voided_at?->toIso8601String(),
            'payable' => $payable === null ? null : [
                'id' => $payable->id,
                'member_name' => $payable->member->full_name,
                'amount' => $payable->amount,
                'amount_reimbursed' => $payable->amount_reimbursed,
                'outstanding' => $payable->outstanding(),
            ],
        ];
    }
}
