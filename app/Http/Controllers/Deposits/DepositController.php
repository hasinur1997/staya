<?php

namespace App\Http\Controllers\Deposits;

use App\Actions\Deposits\ApproveDeposit;
use App\Actions\Deposits\CancelDeposit;
use App\Actions\Deposits\RejectDeposit;
use App\Actions\Deposits\ReverseDeposit;
use App\Actions\Deposits\SubmitDeposit;
use App\Concerns\BusinessCalendar;
use App\Concerns\PresentsDeposits;
use App\Enums\DepositStatus;
use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deposits\DepositReasonRequest;
use App\Http\Requests\Deposits\StoreDepositRequest;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\MessSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    use PresentsDeposits;

    /**
     * Show the user's own deposits, or everyone's with `deposit.view_all`.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Deposit::class);

        $actor = $request->user();
        $viewAll = $actor->can('viewAll', Deposit::class);

        $status = $request->enum('status', DepositStatus::class);
        $memberId = $viewAll && $request->filled('member') ? $request->integer('member') : null;
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) === 1 ? (string) $request->query('month') : null;

        $deposits = Deposit::query()
            ->with('member:id,first_name,last_name,user_id')
            ->when(! $viewAll, fn (Builder $query) => $query->where('member_id', $actor->member->id ?? 0))
            ->when($memberId, fn (Builder $query, int $memberId) => $query->where('member_id', $memberId))
            ->when($status, fn (Builder $query, DepositStatus $status) => $query->where('status', $status))
            ->when($month, function (Builder $query, string $month): void {
                [$from, $to] = BusinessCalendar::monthDates((int) substr($month, 0, 4), (int) substr($month, 5, 2));
                $query->whereBetween('deposit_date', [$from, $to]);
            })
            ->orderByDesc('deposit_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Deposit $deposit) => $this->depositRow($deposit, $actor));

        return Inertia::render('deposits/index', [
            'deposits' => $deposits,
            'filters' => ['status' => $status?->value, 'member' => $memberId, 'month' => $month],
            'statuses' => DepositStatus::options(),
            'members' => $viewAll ? $this->memberOptions() : [],
            'can' => [
                'viewAll' => $viewAll,
                'create' => $actor->can('create', Deposit::class),
                'createForOthers' => $actor->can('createForOthers', Deposit::class),
            ],
        ]);
    }

    /**
     * Show the deposit form.
     */
    public function create(Request $request): Response
    {
        $actor = $request->user();
        $forOthers = $actor->can('createForOthers', Deposit::class);

        abort_unless($forOthers || $actor->can('create', Deposit::class), 403);

        return Inertia::render('deposits/create', [
            'today' => BusinessCalendar::today()->toDateString(),
            'fundStartDate' => MessSetting::current()->fund_start_date?->toDateString(),
            'members' => $forOthers ? $this->memberOptions() : [],
            'ownMemberId' => $actor->can('create', Deposit::class) ? $actor->member?->id : null,
            'can' => [
                'createForOthers' => $forOthers,
                'approve' => $actor->can('review', Deposit::class),
                // Whether "approve on save" may be offered for the user's own deposit.
                'approveOwn' => $actor->member !== null && $actor->can('approveOnSave', [Deposit::class, $actor->member]),
            ],
        ]);
    }

    /**
     * Store a deposit, approving it straight away when asked and allowed.
     */
    public function store(StoreDepositRequest $request, SubmitDeposit $submitDeposit): RedirectResponse
    {
        /** @var Member $member Resolved and checked by the request. */
        $member = $request->member();

        $deposit = $submitDeposit->handle(
            $request->user(),
            $member,
            $request->string('amount')->toString(),
            $request->string('deposit_date')->toString(),
            $request->boolean('approve'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $deposit->status === DepositStatus::Approved
            ? __('Deposit recorded and approved.')
            : __('Deposit submitted for approval.')]);

        return to_route('deposits.show', $deposit);
    }

    /**
     * Show one deposit.
     */
    public function show(Request $request, Deposit $deposit): Response
    {
        Gate::authorize('view', $deposit);

        $actor = $request->user();
        $deposit->load(['member:id,first_name,last_name,user_id', 'submitter:id,name', 'reviewer:id,name', 'reverser:id,name']);

        return Inertia::render('deposits/show', [
            'deposit' => [
                ...$this->depositRow($deposit, $actor),
                'submitted_by' => $deposit->submitter->name,
                'submitted_at' => $deposit->created_at?->toIso8601String(),
                'reviewed_by' => $deposit->reviewer?->name,
                'reviewed_at' => $deposit->reviewed_at?->toIso8601String(),
                'cancelled_at' => $deposit->cancelled_at?->toIso8601String(),
                'reversed_by' => $deposit->reverser?->name,
                'reversed_at' => $deposit->reversed_at?->toIso8601String(),
            ],
            'can' => [
                'viewAny' => $actor->can('viewAny', Deposit::class),
                'cancel' => $actor->can('cancel', $deposit),
                'approve' => $actor->can('approve', $deposit),
                'reject' => $actor->can('reject', $deposit),
                'reverse' => $actor->can('reverse', $deposit),
                'viewMember' => $actor->can('view', $deposit->member),
            ],
        ]);
    }

    /**
     * Cancel one's own pending deposit.
     */
    public function cancel(Request $request, Deposit $deposit, CancelDeposit $cancelDeposit): RedirectResponse
    {
        Gate::authorize('cancel', $deposit);

        $cancelDeposit->handle($request->user(), $deposit);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deposit cancelled.')]);

        return to_route('deposits.show', $deposit);
    }

    /**
     * Approve a pending deposit.
     */
    public function approve(Request $request, Deposit $deposit, ApproveDeposit $approveDeposit): RedirectResponse
    {
        Gate::authorize('approve', $deposit);

        $approveDeposit->handle($request->user(), $deposit);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deposit approved.')]);

        return to_route('deposits.show', $deposit);
    }

    /**
     * Reject a pending deposit with a reason.
     */
    public function reject(DepositReasonRequest $request, Deposit $deposit, RejectDeposit $rejectDeposit): RedirectResponse
    {
        $rejectDeposit->handle($request->user(), $deposit, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deposit rejected.')]);

        return to_route('deposits.show', $deposit);
    }

    /**
     * Reverse an approved deposit with a reason.
     */
    public function reverse(DepositReasonRequest $request, Deposit $deposit, ReverseDeposit $reverseDeposit): RedirectResponse
    {
        $reverseDeposit->handle($request->user(), $deposit, $request->reason());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deposit reversed.')]);

        return to_route('deposits.show', $deposit);
    }

    /**
     * Members a deposit can be recorded for: everyone approved, current or
     * past (the month check happens on save).
     *
     * @return array<int, array{id: int, name: string, status: string}>
     */
    private function memberOptions(): array
    {
        return Member::query()
            ->whereIn('status', [MemberStatus::Active, MemberStatus::Inactive])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (Member $member) => ['id' => $member->id, 'name' => $member->full_name, 'status' => $member->status->value])
            ->values()
            ->all();
    }
}
