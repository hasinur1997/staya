<?php

namespace App\Actions\Deposits;

use App\Actions\Ledger\PostLedgerEntries;
use App\Enums\AuditLog;
use App\Enums\DepositStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Deposit;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApproveDeposit
{
    public function __construct(
        private DepositDateGuard $dateGuard,
        private PostLedgerEntries $postLedgerEntries,
    ) {}

    /**
     * Approve a pending deposit and credit the fund, dated on the deposit's
     * own date so it counts in that month. The row lock plus the status
     * check under it make repeated or parallel approvals fail instead of
     * posting twice. Locks the deposit before the fund, like every deposit
     * action.
     */
    public function handle(User $actor, Deposit $deposit): Deposit
    {
        return DB::transaction(function () use ($actor, $deposit): Deposit {
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);

            Gate::forUser($actor)->authorize('approve', $deposit);

            $depositDate = $deposit->deposit_date->toDateString();
            $this->dateGuard->assertCountable($depositDate);

            $before = $deposit->auditSnapshot();
            $balanceBefore = Fund::locked()->balance;

            $entry = $this->postLedgerEntries->handle($actor, [[
                'type' => LedgerEntryType::Deposit,
                'direction' => LedgerDirection::Credit,
                'amount' => $deposit->amount,
                'entry_date' => $depositDate,
                'source' => $deposit,
            ]], dateField: 'deposit_date')[0];

            $deposit->status = DepositStatus::Approved;
            $deposit->reviewed_by = $actor->id;
            $deposit->reviewed_at = now();
            $deposit->is_self_approved = $deposit->belongsToUser($actor);
            $deposit->save();

            activity()
                ->useLog(AuditLog::Deposit)
                ->performedOn($deposit)
                ->event('updated')
                ->withChanges([
                    'old' => [...$before, 'balance' => $balanceBefore],
                    'attributes' => [...$deposit->auditSnapshot(), 'balance' => Fund::current()->balance],
                ])
                ->withProperties(['self_approved' => $deposit->is_self_approved, 'ledger_entry_id' => $entry->id])
                ->log('deposit_approved');

            return $deposit;
        });
    }
}
