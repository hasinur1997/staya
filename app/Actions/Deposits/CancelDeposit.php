<?php

namespace App\Actions\Deposits;

use App\Enums\AuditLog;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CancelDeposit
{
    public function __construct(private DepositDateGuard $dateGuard) {}

    /**
     * The member withdraws their own pending deposit. Cancelled deposits
     * never touch the ledger.
     */
    public function handle(User $actor, Deposit $deposit): Deposit
    {
        return DB::transaction(function () use ($actor, $deposit): Deposit {
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);

            Gate::forUser($actor)->authorize('cancel', $deposit);

            $this->dateGuard->assertMonthOpen($deposit->deposit_date->toDateString());

            $before = $deposit->auditSnapshot();

            $deposit->status = DepositStatus::Cancelled;
            $deposit->cancelled_at = now();
            $deposit->save();

            activity()
                ->useLog(AuditLog::Deposit)
                ->performedOn($deposit)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $deposit->auditSnapshot()])
                ->log('deposit_cancelled');

            return $deposit;
        });
    }
}
