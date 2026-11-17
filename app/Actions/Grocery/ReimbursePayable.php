<?php

namespace App\Actions\Grocery;

use App\Actions\Ledger\PostLedgerEntries;
use App\Concerns\BusinessCalendar;
use App\Concerns\Money;
use App\Enums\AuditLog;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\BuyerPayable;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReimbursePayable
{
    public function __construct(private PostLedgerEntries $postLedgerEntries) {}

    /**
     * Record cash handed to a buyer for a purchase the fund could not
     * cover: a ledger debit dated today (the day of payment) and the same
     * amount off the payable. It can exceed neither what is still owed nor
     * what the fund holds. Locks the payable, then the fund.
     */
    public function handle(User $actor, BuyerPayable $payable, string $amount): BuyerPayable
    {
        return DB::transaction(function () use ($actor, $payable, $amount): BuyerPayable {
            $payable = BuyerPayable::query()->lockForUpdate()->findOrFail($payable->id);

            Gate::forUser($actor)->authorize('reimburse', $payable);

            $amount = bcadd(Money::numeric($amount), '0', Money::SCALE);
            $outstanding = $payable->outstanding();

            if (bccomp($amount, $outstanding, Money::SCALE) > 0) {
                throw ValidationException::withMessages([
                    'amount' => __('Only :amount is still owed for this purchase.', ['amount' => Money::format($outstanding)]),
                ]);
            }

            $before = $payable->auditSnapshot();
            $balanceBefore = Fund::locked()->balance;

            $entry = $this->postLedgerEntries->handle($actor, [[
                'type' => LedgerEntryType::Reimbursement,
                'direction' => LedgerDirection::Debit,
                'amount' => $amount,
                'entry_date' => BusinessCalendar::today()->toDateString(),
                'source' => $payable,
            ]], dateField: 'amount')[0];

            $payable->amount_reimbursed = bcadd(Money::numeric($payable->amount_reimbursed), $amount, Money::SCALE);
            $payable->save();

            activity()
                ->useLog(AuditLog::Ledger)
                ->performedOn($payable)
                ->event('updated')
                ->withChanges([
                    'old' => [...$before, 'balance' => $balanceBefore],
                    'attributes' => [...$payable->auditSnapshot(), 'balance' => Fund::current()->balance],
                ])
                ->withProperties(['ledger_entry_id' => $entry->id, 'amount' => $amount])
                ->log('payable_reimbursed');

            return $payable;
        });
    }
}
