<?php

namespace App\Actions\Reports;

/**
 * The figures of one month: what was deposited and spent, how many members
 * lived in the mess, the equal share each owes, and each member's due or
 * credit. Money is decimal strings at scale 2.
 *
 * @phpstan-type MemberRow array{member_id: int, name: string, deposits: string, share: string, due_credit: string}
 */
final readonly class MonthlyReport
{
    /**
     * @param  string  $deposits  Approved deposits dated in the month.
     * @param  string  $expenses  Grand totals of active purchases dated in the month.
     * @param  string  $share  Each active member's equal share, truncated to 2 places.
     * @param  string  $leftover  Expenses the truncated shares leave uncovered (paisa).
     * @param  list<MemberRow>  $members  One row per member active in the month.
     */
    public function __construct(
        public string $from,
        public string $to,
        public string $deposits,
        public string $expenses,
        public int $activeMembers,
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
