<?php

namespace App\Actions\Members;

use App\Concerns\Money;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\GroceryPurchase;
use App\Models\Member;

class SummarizeMemberAccount
{
    /**
     * What one member has put in and spent, all time and in a month
     * (Y-m-d bounds, inclusive). Only approved deposits count, and voided
     * purchases are left out. Amounts are decimal strings added with bcmath.
     *
     * @return array{deposited: array{total: string, month: string}, bajar: array{total: string, month: string}}
     */
    public function handle(Member $member, string $monthFirst, string $monthLast): array
    {
        $deposited = ['total' => '0.00', 'month' => '0.00'];

        $approved = Deposit::query()
            ->where('member_id', $member->id)
            ->where('status', DepositStatus::Approved)
            ->get(['amount', 'deposit_date']);

        foreach ($approved as $deposit) {
            $amount = Money::numeric($deposit->amount);
            $deposited['total'] = bcadd($deposited['total'], $amount, Money::SCALE);

            if ($this->within($deposit->deposit_date->toDateString(), $monthFirst, $monthLast)) {
                $deposited['month'] = bcadd($deposited['month'], $amount, Money::SCALE);
            }
        }

        $bajar = ['total' => '0.00', 'month' => '0.00'];

        $purchases = GroceryPurchase::query()
            ->active()
            ->where('buyer_member_id', $member->id)
            ->get(['grand_total', 'purchase_date']);

        foreach ($purchases as $purchase) {
            $total = Money::numeric($purchase->grand_total);
            $bajar['total'] = bcadd($bajar['total'], $total, Money::SCALE);

            if ($this->within($purchase->purchase_date->toDateString(), $monthFirst, $monthLast)) {
                $bajar['month'] = bcadd($bajar['month'], $total, Money::SCALE);
            }
        }

        return ['deposited' => $deposited, 'bajar' => $bajar];
    }

    private function within(string $date, string $first, string $last): bool
    {
        return $date >= $first && $date <= $last;
    }
}
