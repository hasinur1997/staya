<?php

namespace App\Actions\Deposits;

use App\Enums\AuditLog;
use App\Enums\DepositMethod;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SubmitDeposit
{
    public function __construct(
        private DepositDateGuard $dateGuard,
        private ApproveDeposit $approveDeposit,
    ) {}

    /**
     * Record a pending cash deposit, by the member or by staff on their
     * behalf. With `$approve`, staff who may approve it do so in the same
     * transaction; the self-approval rule still applies.
     */
    public function handle(User $actor, Member $member, string $amount, string $depositDate, bool $approve = false): Deposit
    {
        return DB::transaction(function () use ($actor, $member, $amount, $depositDate, $approve): Deposit {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            Gate::forUser($actor)->authorize('createFor', [Deposit::class, $member]);

            if ($approve) {
                Gate::forUser($actor)->authorize('approveOnSave', [Deposit::class, $member]);
            }

            $this->dateGuard->assertCountable($depositDate);

            if (! $member->isActiveInMonthOf($depositDate)) {
                throw ValidationException::withMessages([
                    'deposit_date' => __(':name was not living in the mess in that month.', ['name' => $member->full_name]),
                ]);
            }

            $deposit = Deposit::query()->create([
                'member_id' => $member->id,
                'amount' => $amount,
                'deposit_date' => $depositDate,
                'method' => DepositMethod::Cash,
                'submitted_by' => $actor->id,
            ]);

            $isOwn = $member->user_id === $actor->id;

            activity()
                ->useLog(AuditLog::Deposit)
                ->performedOn($deposit)
                ->event('created')
                ->withChanges(['attributes' => $deposit->refresh()->auditSnapshot()])
                ->log($isOwn ? 'deposit_submitted' : 'deposit_recorded');

            if ($approve) {
                $deposit = $this->approveDeposit->handle($actor, $deposit);
            }

            return $deposit;
        });
    }
}
