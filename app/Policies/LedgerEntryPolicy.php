<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class LedgerEntryPolicy
{
    /**
     * Determine whether the user can view the ledger transactions.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::LedgerViewTransactions->value);
    }

    /**
     * Determine whether the user can post a manual adjustment.
     */
    public function adjust(User $actor): bool
    {
        return $actor->can(Permission::LedgerAdjust->value);
    }
}
