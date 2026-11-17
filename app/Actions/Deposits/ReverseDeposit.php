<?php

namespace App\Actions\Deposits;

use App\Actions\Ledger\PostLedgerEntries;
use App\Actions\Ledger\ReverseLedgerEntry;
use App\Enums\AuditLog;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReverseDeposit
{
    public function __construct(
        private ReverseLedgerEntry $reverseLedgerEntry,
        private PostLedgerEntries $postLedgerEntries,
    ) {}

    /**
     * Undo an approved deposit: post the opposite of its credit, dated in
     * the deposit's month, and mark it Reversed so it no longer counts. The
     * ledger refuses it when the month is closed or the fund would drop
     * below ৳0; the shortfall is reported on the reason field, where the
     * reviewer is typing.
     */
    public function handle(User $actor, Deposit $deposit, string $reason): Deposit
    {
        return DB::transaction(function () use ($actor, $deposit, $reason): Deposit {
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);

            Gate::forUser($actor)->authorize('reverse', $deposit);

            $before = $deposit->auditSnapshot();
            $balanceBefore = Fund::locked()->balance;

            $entry = $this->postLedgerEntries->handle(
                $actor,
                [$this->reverseLedgerEntry->reversalOf($deposit->creditEntry()->firstOrFail(), $reason)],
                amountField: 'reason',
                dateField: 'reason',
            )[0];

            $deposit->status = DepositStatus::Reversed;
            $deposit->reversal_reason = $reason;
            $deposit->reversed_by = $actor->id;
            $deposit->reversed_at = now();
            $deposit->save();

            activity()
                ->useLog(AuditLog::Deposit)
                ->performedOn($deposit)
                ->event('updated')
                ->withChanges([
                    'old' => [...$before, 'balance' => $balanceBefore],
                    'attributes' => [...$deposit->auditSnapshot(), 'balance' => Fund::current()->balance],
                ])
                ->withProperties(['ledger_entry_id' => $entry->id])
                ->log('deposit_reversed');

            return $deposit;
        });
    }
}
