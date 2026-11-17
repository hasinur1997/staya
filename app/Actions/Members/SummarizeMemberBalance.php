<?php

namespace App\Actions\Members;

use App\Actions\Reports\CalculateMonthlyReport;
use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Models\BuyerPayable;
use App\Models\Member;

class SummarizeMemberBalance
{
    /** Months shown at most, counting back from the current one. */
    public const int MAX_MONTHS = 24;

    public function __construct(private CalculateMonthlyReport $calculate) {}

    /**
     * What the member still has to pay the mess and what they can take from
     * the fund. Each month they lived in the mess stands alone (no
     * carry-forward): a negative due-or-credit is a due, a positive one is a
     * credit. `to_pay` adds up the dues; `fund_owes` is what the fund still
     * owes them for purchases it could not cover (open payables). Amounts
     * are decimal strings added with bcmath.
     *
     * @return array{to_pay: string, credit: string, fund_owes: string, months: list<array{month: string, deposits: string, share: string, meals: int, due_credit: string}>}
     */
    public function handle(Member $member): array
    {
        $firstStay = $member->stays()->min('joined_on');
        $first = $firstStay !== null
            ? substr((string) $firstStay, 0, 7)
            : BusinessCalendar::today()->format('Y-m');
        $cursor = BusinessCalendar::today()->startOfMonth();

        $months = [];
        $toPay = '0.00';
        $credit = '0.00';

        for ($step = 0; $step < self::MAX_MONTHS && $cursor->format('Y-m') >= $first; $step++, $cursor = $cursor->subMonthsNoOverflow()) {
            $row = $this->calculate->handle($cursor->year, $cursor->month)->rowFor($member->id);

            if ($row === null) {
                continue;
            }

            $months[] = [
                'month' => $cursor->format('Y-m'),
                'deposits' => $row['deposits'],
                'share' => $row['share'],
                'meals' => $row['meals'],
                'due_credit' => $row['due_credit'],
            ];

            if (bccomp(Money::numeric($row['due_credit']), '0', Money::SCALE) < 0) {
                $toPay = bcadd($toPay, ltrim(Money::numeric($row['due_credit']), '-'), Money::SCALE);
            } else {
                $credit = bcadd($credit, Money::numeric($row['due_credit']), Money::SCALE);
            }
        }

        $fundOwes = '0.00';

        foreach (BuyerPayable::query()->open()->where('member_id', $member->id)->get(['amount', 'amount_reimbursed']) as $payable) {
            $fundOwes = bcadd($fundOwes, bcsub(Money::numeric($payable->amount), Money::numeric($payable->amount_reimbursed), Money::SCALE), Money::SCALE);
        }

        return ['to_pay' => $toPay, 'credit' => $credit, 'fund_owes' => $fundOwes, 'months' => $months];
    }
}
