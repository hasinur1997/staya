<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\BuyerPayable;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BuyerPayablePolicy
{
    /**
     * Determine whether the user can see the open payables list.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::LedgerReimburse->value);
    }

    /**
     * Determine whether the user can record a reimbursement for the payable.
     */
    public function reimburse(User $actor, BuyerPayable $payable): Response
    {
        if (! $actor->can(Permission::LedgerReimburse->value)) {
            return Response::deny();
        }

        return $payable->isOpen()
            ? Response::allow()
            : Response::deny(__('Nothing is owed for this purchase any more.'));
    }
}
