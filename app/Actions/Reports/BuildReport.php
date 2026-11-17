<?php

namespace App\Actions\Reports;

use App\Actions\Ledger\SummarizeLedgerPeriod;
use App\Concerns\Money;
use App\Enums\DepositStatus;
use App\Models\BuyerPayable;
use App\Models\Deposit;
use App\Models\Fund;
use App\Models\Member;
use App\Models\MonthClosure;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Everything the report page shows for a month or a custom range of
 * business dates, limited to what the viewer may see: the per-member
 * table, pending/rejected and self-approved deposits need
 * `report.view_all`; opening and closing balance need the balance right.
 */
class BuildReport
{
    public function __construct(
        private CalculateMonthlyReport $calculate,
        private SummarizeLedgerPeriod $summarize,
    ) {}

    /**
     * @param  string|null  $month  `Y-m` when the period is exactly one month.
     * @param  int|null  $memberId  Narrow the member table to one member.
     * @return array<string, mixed>
     */
    public function handle(User $actor, string $from, string $to, ?string $month = null, ?int $memberId = null): array
    {
        $figures = $this->calculate->forRange($from, $to);
        $viewAll = Gate::forUser($actor)->allows('viewAll', MonthlyReport::class);
        $ownId = $actor->member?->id;

        $rows = array_values(array_filter(
            $figures->members,
            fn (array $row): bool => $viewAll ? ($memberId === null || $row['member_id'] === $memberId) : $row['member_id'] === $ownId,
        ));

        return [
            'from' => $from,
            'to' => $to,
            'month' => $month,
            'figures' => [
                'deposits' => $figures->deposits,
                'expenses' => $figures->expenses,
                'split' => $figures->split->value,
                'active_members' => $figures->activeMembers,
                'total_meals' => $figures->totalMeals,
                'share' => $figures->share,
                'leftover' => $figures->leftover,
            ],
            'ledger' => Gate::forUser($actor)->allows('viewBalance', Fund::class)
                ? Arr::only($this->summarize->handle($from, $to), ['opening', 'closing', 'adjustments', 'reimbursements'])
                : null,
            'members' => $rows,
            'unapproved' => $viewAll ? [
                'pending' => $this->depositSummary(DepositStatus::Pending, $from, $to),
                'rejected' => $this->depositSummary(DepositStatus::Rejected, $from, $to),
            ] : null,
            'self_approved' => $viewAll ? $this->selfApproved($from, $to) : null,
            'open_payables' => Gate::forUser($actor)->allows('viewAny', BuyerPayable::class) ? $this->openPayables() : null,
            'closure' => $month !== null ? $this->closure($from) : null,
        ];
    }

    /**
     * @return array{count: int, amount: string}
     */
    private function depositSummary(DepositStatus $status, string $from, string $to): array
    {
        $amount = '0.00';
        $deposits = Deposit::query()->where('status', $status)->whereBetween('deposit_date', [$from, $to])->pluck('amount');

        foreach ($deposits as $value) {
            $amount = bcadd($amount, Money::numeric($value), Money::SCALE);
        }

        return ['count' => $deposits->count(), 'amount' => $amount];
    }

    /**
     * Approved deposits the approver also submitted, for review.
     *
     * @return array<int, array{id: int, member_id: int, member_name: string, amount: string, deposit_date: string}>
     */
    private function selfApproved(string $from, string $to): array
    {
        return Deposit::query()
            ->with('member:id,first_name,last_name,name_bn')
            ->where('status', DepositStatus::Approved)
            ->where('is_self_approved', true)
            ->whereBetween('deposit_date', [$from, $to])
            ->orderBy('deposit_date')->orderBy('id')
            ->get()
            ->map(fn (Deposit $deposit): array => [
                'id' => $deposit->id,
                'member_id' => $deposit->member_id,
                'member_name' => $deposit->member->full_name,
                'amount' => $deposit->amount,
                'deposit_date' => $deposit->deposit_date->toDateString(),
            ])->values()->all();
    }

    /**
     * What the fund owes buyers right now.
     *
     * @return array{count: int, amount: string}
     */
    private function openPayables(): array
    {
        $amount = '0.00';
        $payables = BuyerPayable::query()->open()->get();

        foreach ($payables as $payable) {
            $amount = bcadd($amount, Money::numeric($payable->outstanding()), Money::SCALE);
        }

        return ['count' => $payables->count(), 'amount' => $amount];
    }

    /**
     * @return array{status: string, closed_at: string|null, closed_by: string|null, reopened_at: string|null, reopened_by: string|null, reopen_reason: string|null}
     */
    private function closure(string $from): array
    {
        $closure = MonthClosure::query()->with(['closedBy:id,name', 'reopenedBy:id,name'])
            ->where('month', MonthClosure::monthOf($from)->toDateString())->first();

        return [
            'status' => $closure !== null && $closure->reopened_at === null ? 'closed' : 'open',
            'closed_at' => $closure?->closed_at->toIso8601String(),
            'closed_by' => $closure?->closedBy->name,
            'reopened_at' => $closure?->reopened_at?->toIso8601String(),
            'reopened_by' => $closure?->reopenedBy?->name,
            'reopen_reason' => $closure?->reopen_reason,
        ];
    }
}
