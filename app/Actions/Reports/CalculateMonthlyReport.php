<?php

namespace App\Actions\Reports;

use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Member;

/**
 * Works out one month (evaluated in Asia/Dhaka) from the records dated in
 * it. A month never reads another month: due or credit is the member's
 * approved deposits minus the equal share, with no carry-forward.
 *
 * - Deposits count by their own date, not by when they were approved.
 * - Expenses are the full grand totals of purchases, including the part a
 *   buyer payable covers. Adjustments and reimbursements are not shared.
 * - A member who lived in the mess on any day of the month is active.
 */
class CalculateMonthlyReport
{
    public function handle(int $year, int $month): MonthlyReport
    {
        [$first, $last] = BusinessCalendar::monthDates($year, $month);

        $depositsByMember = [];
        $deposits = '0.00';

        $approved = Deposit::query()
            ->where('status', DepositStatus::Approved)
            ->whereBetween('deposit_date', [$first, $last])
            ->get(['member_id', 'amount']);

        foreach ($approved as $deposit) {
            $amount = Money::numeric($deposit->amount);
            $deposits = bcadd($deposits, $amount, Money::SCALE);
            $depositsByMember[$deposit->member_id] = bcadd($depositsByMember[$deposit->member_id] ?? '0.00', $amount, Money::SCALE);
        }

        $expenses = '0.00';

        foreach (GroceryPurchase::query()->active()->whereBetween('purchase_date', [$first, $last])->pluck('grand_total') as $total) {
            $expenses = bcadd($expenses, Money::numeric($total), Money::SCALE);
        }

        $members = Member::query()
            ->activeInMonth($year, $month)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name']);

        $count = $members->count();
        $share = $count > 0 ? bcdiv($expenses, (string) $count, Money::SCALE) : '0.00';
        $leftover = $count > 0 ? bcsub($expenses, bcmul($share, (string) $count, Money::SCALE), Money::SCALE) : '0.00';

        $rows = [];

        foreach ($members as $member) {
            $memberDeposits = $depositsByMember[$member->id] ?? '0.00';

            $rows[] = [
                'member_id' => $member->id,
                'name' => $member->full_name,
                'deposits' => $memberDeposits,
                'share' => $share,
                'due_credit' => bcsub($memberDeposits, $share, Money::SCALE),
            ];
        }

        return new MonthlyReport($year, $month, $deposits, $expenses, $count, $share, $leftover, $rows);
    }
}
