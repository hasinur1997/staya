<?php

namespace App\Enums;

/**
 * The seeded roles. Their names are locked so code can find them (e.g. the
 * last-Admin rule). Never use these for access checks: check a Permission.
 */
enum SystemRole: string
{
    case Admin = 'Admin';
    case Manager = 'Manager';
    case Member = 'Member';

    /**
     * Default permissions. Admin always holds the whole catalog.
     *
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        $member = [
            Permission::GroceryView,
            Permission::GroceryCreate,
            Permission::GroceryUpdateOwn,
            Permission::GroceryDeleteOwn,
            Permission::DepositViewOwn,
            Permission::DepositCreateOwn,
            Permission::DepositCancelOwn,
            Permission::ReportViewOwn,
            Permission::ReportViewAll,
            Permission::LedgerViewBalance,
        ];

        return match ($this) {
            self::Admin => Permission::cases(),
            self::Member => $member,
            self::Manager => [
                ...$member,
                Permission::MemberView,
                Permission::GroceryCreateForOthers,
                Permission::GroceryUpdateAny,
                Permission::GroceryDeleteAny,
                Permission::DepositViewAll,
                Permission::DepositCreateForOthers,
                Permission::DepositApprove,
                Permission::LedgerViewTransactions,
            ],
        };
    }
}
