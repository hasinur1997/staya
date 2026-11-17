<?php

namespace App\Actions\Reports;

use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Enums\CostSplit;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Meal;
use App\Models\Member;
use App\Models\MessSetting;
use App\Models\MonthClosure;
use Illuminate\Database\Eloquent\Builder;

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

        return $this->forRange($first, $last);
    }

    /**
     * The same figures for any run of business dates (Y-m-d, inclusive).
     * With a range the share is the range's expenses split between the
     * members who lived in the mess on any day of it.
     */
    public function forRange(string $first, string $last): MonthlyReport
    {
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

        $split = $this->splitFor($first, $last);

        $mealsByMember = [];
        $totalMeals = 0;

        foreach (Meal::query()->whereBetween('meal_date', [$first, $last])->get(['member_id', 'count']) as $meal) {
            $mealsByMember[$meal->member_id] = ($mealsByMember[$meal->member_id] ?? 0) + $meal->count;
            $totalMeals += $meal->count;
        }

        $members = Member::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('stays', fn (Builder $stays) => $stays->overlapping($first, $last))
                ->orWhereIn('id', array_keys($mealsByMember)))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            ->get(['id', 'first_name', 'last_name']);

        $count = $members->count();

        if ($split === CostSplit::Meals) {
            $share = $totalMeals > 0 ? bcdiv($expenses, (string) $totalMeals, Money::SCALE) : '0.00';
        } else {
            $share = $count > 0 ? bcdiv($expenses, (string) $count, Money::SCALE) : '0.00';
        }

        $rows = [];
        $charged = '0.00';

        foreach ($members as $member) {
            $memberDeposits = $depositsByMember[$member->id] ?? '0.00';
            $meals = $mealsByMember[$member->id] ?? 0;

            // Each meal split share is expenses x meals / total meals,
            // truncated once, so the paisa left over stays under one per member.
            $memberShare = $split === CostSplit::Meals
                ? ($totalMeals > 0 ? bcdiv(bcmul($expenses, (string) $meals, Money::SCALE), (string) $totalMeals, Money::SCALE) : '0.00')
                : $share;
            $charged = bcadd($charged, $memberShare, Money::SCALE);

            $rows[] = [
                'member_id' => $member->id,
                'name' => $member->full_name,
                'deposits' => $memberDeposits,
                'meals' => $meals,
                'share' => $memberShare,
                'due_credit' => bcsub($memberDeposits, $memberShare, Money::SCALE),
            ];
        }

        $leftover = ($split === CostSplit::Meals ? $totalMeals > 0 : $count > 0)
            ? bcsub($expenses, $charged, Money::SCALE)
            : $expenses;

        return new MonthlyReport($first, $last, $split, $deposits, $expenses, $count, $totalMeals, $share, $leftover, $rows);
    }

    /**
     * The split for a period. A single month that is closed keeps the split
     * it was closed with; anything else follows the current setting.
     */
    private function splitFor(string $first, string $last): CostSplit
    {
        if (substr($first, 0, 7) === substr($last, 0, 7)) {
            $closure = MonthClosure::query()->closed()->where('month', MonthClosure::monthOf($first)->toDateString())->first();

            if ($closure?->cost_split !== null) {
                return $closure->cost_split;
            }
        }

        return MessSetting::current()->cost_split;
    }
}
