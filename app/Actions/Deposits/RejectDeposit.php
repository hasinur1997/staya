<?php

namespace App\Actions\Deposits;

use App\Enums\AuditLog;
use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RejectDeposit
{
    public function __construct(private DepositDateGuard $dateGuard) {}

    /**
     * Reject a pending deposit with a reason the member will see. The fund
     * is never touched.
     */
    public function handle(User $actor, Deposit $deposit, string $reason): Deposit
    {
        return DB::transaction(function () use ($actor, $deposit, $reason): Deposit {
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);

            Gate::forUser($actor)->authorize('reject', $deposit);

            $this->dateGuard->assertMonthOpen($deposit->deposit_date->toDateString(), 'reason');

            $before = $deposit->auditSnapshot();

            $deposit->status = DepositStatus::Rejected;
            $deposit->rejection_reason = $reason;
            $deposit->reviewed_by = $actor->id;
            $deposit->reviewed_at = now();
            $deposit->save();

            activity()
                ->useLog(AuditLog::Deposit)
                ->performedOn($deposit)
                ->event('updated')
                ->withChanges(['old' => $before, 'attributes' => $deposit->auditSnapshot()])
                ->log('deposit_rejected');

            return $deposit;
        });
    }
}
