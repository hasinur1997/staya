<?php

namespace App\Actions\Grocery;

use App\Actions\Ledger\PostLedgerEntries;
use App\Actions\Ledger\ReverseLedgerEntry;
use App\Concerns\Money;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\BuyerPayable;
use App\Models\Fund;
use App\Models\GroceryPurchase;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Splits a purchase's total between the fund and the buyer payable, and
 * posts whatever that changes. Used when a purchase is recorded, edited or
 * voided (a void settles to ৳0), always inside the caller's transaction
 * with the purchase row locked.
 *
 * The fund never goes below ৳0:
 * - A higher total is paid from the fund as far as it reaches; the rest is
 *   added to the payable.
 * - A lower total first reduces the unpaid part of the payable, then the
 *   fund's expense. If even the reimbursed part is more than the new total,
 *   the reimbursements are reversed (the money goes back to the fund) and
 *   the fund pays the new total itself.
 *
 * A changed expense is never edited: the old entry is reversed and a new
 * one posted in the same batch.
 */
class SettlePurchase
{
    public function __construct(
        private PostLedgerEntries $postLedgerEntries,
        private ReverseLedgerEntry $reverseLedgerEntry,
    ) {}

    /**
     * @param  string  $total  What the purchase should now cost; "0" for a void.
     * @param  string  $date  The purchase date the new expense is posted on.
     * @param  string  $reason  Recorded on any reversal entries.
     * @param  string  $field  Form field that carries closed-month errors.
     */
    public function handle(User $actor, GroceryPurchase $purchase, string $total, string $date, string $reason, string $field = 'purchase_date'): void
    {
        DB::transaction(function () use ($actor, $purchase, $total, $date, $reason, $field): void {
            $payable = $purchase->payable()->lockForUpdate()->first();
            $balance = Money::numeric(Fund::locked()->balance);
            $expense = $purchase->expenseEntry()->first();

            $newTotal = bcadd(Money::numeric($total), '0', Money::SCALE);
            $oldFundPaid = Money::numeric($expense->amount ?? '0.00');
            $oldPayable = Money::numeric($payable->amount ?? '0.00');
            $oldReimbursed = Money::numeric($payable->amount_reimbursed ?? '0.00');
            $oldTotal = bcadd($oldFundPaid, $oldPayable, Money::SCALE);

            $reimbursed = $oldReimbursed;
            $reverseReimbursements = false;

            if (bccomp($newTotal, $oldTotal, Money::SCALE) >= 0) {
                $wanted = bcadd($oldFundPaid, bcsub($newTotal, $oldTotal, Money::SCALE), Money::SCALE);
                $available = bcadd($balance, $oldFundPaid, Money::SCALE);

                $fundPaid = self::min($wanted, $available);
                $owed = bcsub($newTotal, $fundPaid, Money::SCALE);
            } else {
                $decrease = bcsub($oldTotal, $newTotal, Money::SCALE);
                $offUnpaid = self::min($decrease, bcsub($oldPayable, $oldReimbursed, Money::SCALE));
                $offFund = bcsub($decrease, $offUnpaid, Money::SCALE);

                if (bccomp($offFund, $oldFundPaid, Money::SCALE) <= 0) {
                    $fundPaid = bcsub($oldFundPaid, $offFund, Money::SCALE);
                    $owed = bcsub($oldPayable, $offUnpaid, Money::SCALE);
                } else {
                    // More was reimbursed than the purchase now costs.
                    $reverseReimbursements = true;
                    $fundPaid = $newTotal;
                    $owed = '0.00';
                    $reimbursed = '0.00';
                }
            }

            $entries = [];
            $expenseChanges = $expense === null
                ? bccomp($fundPaid, '0', Money::SCALE) > 0
                : bccomp($fundPaid, $oldFundPaid, Money::SCALE) !== 0 || $expense->entry_date->toDateString() !== $date;

            if ($expense !== null && $expenseChanges) {
                $entries[] = $this->reverseLedgerEntry->reversalOf($expense, $reason);
            }

            if ($reverseReimbursements && $payable !== null) {
                foreach ($payable->reimbursementEntries()->orderBy('id')->get() as $reimbursement) {
                    /** @var LedgerEntry $reimbursement */
                    $entries[] = $this->reverseLedgerEntry->reversalOf($reimbursement, $reason);
                }
            }

            if ($expenseChanges && bccomp($fundPaid, '0', Money::SCALE) > 0) {
                $entries[] = [
                    'type' => LedgerEntryType::GroceryExpense,
                    'direction' => LedgerDirection::Debit,
                    'amount' => $fundPaid,
                    'entry_date' => $date,
                    'source' => $purchase,
                ];
            }

            if ($entries !== []) {
                $this->postLedgerEntries->handle($actor, $entries, amountField: $field, dateField: $field);
            }

            if ($payable !== null) {
                $payable->amount = $owed;
                $payable->amount_reimbursed = $reimbursed;
                $payable->member_id = $purchase->buyer_member_id;
                $payable->save();
            } elseif (bccomp($owed, '0', Money::SCALE) > 0) {
                BuyerPayable::query()->create([
                    'grocery_purchase_id' => $purchase->id,
                    'member_id' => $purchase->buyer_member_id,
                    'amount' => $owed,
                ]);
            }
        });
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    private static function min(string $a, string $b): string
    {
        return bccomp($a, $b, Money::SCALE) <= 0 ? $a : $b;
    }
}
