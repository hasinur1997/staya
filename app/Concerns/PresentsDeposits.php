<?php

namespace App\Concerns;

use App\Models\Deposit;
use App\Models\User;

/**
 * How a deposit is sent to the frontend: money as a decimal string, the
 * date as a Y-m-d business date, and what the viewer may do with it.
 */
trait PresentsDeposits
{
    /**
     * @return array<string, mixed>
     */
    protected function depositRow(Deposit $deposit, User $viewer): array
    {
        return [
            'id' => $deposit->id,
            'member_id' => $deposit->member_id,
            'member_name' => $deposit->member->full_name,
            'amount' => $deposit->amount,
            'deposit_date' => $deposit->deposit_date->toDateString(),
            'method' => $deposit->method->value,
            'method_label' => $deposit->method->label(),
            'status' => $deposit->status->value,
            'status_label' => $deposit->status->label(),
            'rejection_reason' => $deposit->rejection_reason,
            'reversal_reason' => $deposit->reversal_reason,
            'is_self_approved' => $deposit->is_self_approved,
            'is_own' => $deposit->belongsToUser($viewer),
            'can' => [
                'cancel' => $viewer->can('cancel', $deposit),
            ],
        ];
    }
}
