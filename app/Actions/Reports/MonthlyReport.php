<?php

namespace App\Actions\Reports;

use App\Enums\CostSplit;

/**
 * The figures of one month: what was deposited and spent, how many members
 * lived in the mess, what each owes, and each member's due or credit. Money
 * is decimal strings at scale 2.
 *
 * @phpstan-type MemberRow array{member_id: int, name: string, deposits: string, meals: int, share: string, due_credit: string}
 */
final readonly class MonthlyReport
{
    /**
     * @param  CostSplit  $split  How the expenses were divided.
     * @param  string  $deposits  Approved deposits dated in the month.
     * @param  string  $expenses  Grand totals of active purchases dated in the month.
     * @param  string  $share  The equal share each member owes (equal split), or the cost of one meal (meal split), truncated to 2 places.
     * @param  int  $totalMeals  Meals recorded in the period, all members.
     * @param  string  $leftover  Expenses the truncated shares leave uncovered (paisa).
     * @param  list<MemberRow>  $members  One row per member active in the month, or with meals in it.
     */
    public function __construct(
        public string $from,
        public string $to,
        public CostSplit $split,
        public string $deposits,
        public string $expenses,
        public int $activeMembers,
        public int $totalMeals,
        public string $share,
        public string $leftover,
        public array $members,
    ) {}

    /**
     * @return MemberRow|null
     */
    public function rowFor(int $memberId): ?array
    {
        foreach ($this->members as $row) {
            if ($row['member_id'] === $memberId) {
                return $row;
            }
        }

        return null;
    }
}
