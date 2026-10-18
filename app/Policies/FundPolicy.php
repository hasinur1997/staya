<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MessSetting;
use App\Models\User;

class FundPolicy
{
    /**
     * Determine whether the user can see the shared fund balance.
     *
     * The "members can view the balance" setting limits users who only hold
     * `ledger.view_balance`. Anyone who may view the transactions already
     * sees every entry, so the setting does not hide the total from them.
     */
    public function viewBalance(User $actor): bool
    {
        if (! $actor->can(Permission::LedgerViewBalance->value)) {
            return false;
        }

        return MessSetting::current()->members_can_view_balance
            || $actor->can(Permission::LedgerViewTransactions->value);
    }
}
