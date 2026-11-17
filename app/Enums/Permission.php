<?php

namespace App\Enums;

/**
 * The permission catalog from docs/project-overview.md. Seeded into the
 * `permissions` table; the UI never creates or deletes permissions.
 */
enum Permission: string
{
    case MemberView = 'member.view';
    case MemberCreate = 'member.create';
    case MemberUpdate = 'member.update';
    case MemberDeactivate = 'member.deactivate';
    case MemberDelete = 'member.delete';
    case MemberViewNid = 'member.view_nid';
    case MemberApproveRegistration = 'member.approve_registration';

    case GroceryView = 'grocery.view';
    case GroceryCreate = 'grocery.create';
    case GroceryCreateForOthers = 'grocery.create_for_others';
    case GroceryUpdateOwn = 'grocery.update_own';
    case GroceryDeleteOwn = 'grocery.delete_own';
    case GroceryUpdateAny = 'grocery.update_any';
    case GroceryDeleteAny = 'grocery.delete_any';

    case DepositViewOwn = 'deposit.view_own';
    case DepositViewAll = 'deposit.view_all';
    case DepositCreateOwn = 'deposit.create_own';
    case DepositCreateForOthers = 'deposit.create_for_others';
    case DepositCancelOwn = 'deposit.cancel_own';
    case DepositApprove = 'deposit.approve';
    case DepositApproveOwn = 'deposit.approve_own';
    case DepositReverse = 'deposit.reverse';

    case LedgerViewBalance = 'ledger.view_balance';
    case LedgerViewTransactions = 'ledger.view_transactions';
    case LedgerAdjust = 'ledger.adjust';
    case LedgerReimburse = 'ledger.reimburse';

    case ReportViewOwn = 'report.view_own';
    case ReportViewAll = 'report.view_all';
    case ReportCloseMonth = 'report.close_month';

    case UserView = 'user.view';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDeactivate = 'user.deactivate';

    case RoleView = 'role.view';
    case RoleManage = 'role.manage';

    case MealViewOwn = 'meal.view_own';
    case MealViewAll = 'meal.view_all';
    case MealRecord = 'meal.record';

    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';
    case SettingsResetData = 'settings.reset_data';

    case AuditView = 'audit.view';

    /**
     * The catalog resource, e.g. "deposit".
     */
    public function group(): string
    {
        return strstr($this->value, '.', true) ?: $this->value;
    }

    /**
     * A human-readable label for the role editor.
     */
    public function description(): string
    {
        return match ($this) {
            self::MemberView => __('View members'),
            self::MemberCreate => __('Register members'),
            self::MemberUpdate => __('Edit members'),
            self::MemberDeactivate => __('Deactivate and reactivate members'),
            self::MemberDelete => __('Delete members without financial records'),
            self::MemberViewNid => __('View NID number and attachment'),
            self::MemberApproveRegistration => __('Approve or reject registrations'),
            self::GroceryView => __('View grocery purchases'),
            self::GroceryCreate => __('Record own grocery purchases'),
            self::GroceryCreateForOthers => __('Record purchases for other members'),
            self::GroceryUpdateOwn => __('Edit own purchases within the edit window'),
            self::GroceryDeleteOwn => __('Delete own purchases within the edit window'),
            self::GroceryUpdateAny => __('Edit any purchase'),
            self::GroceryDeleteAny => __('Delete any purchase'),
            self::DepositViewOwn => __('View own deposits'),
            self::DepositViewAll => __('View all deposits'),
            self::DepositCreateOwn => __('Submit own deposits'),
            self::DepositCreateForOthers => __('Record deposits for other members'),
            self::DepositCancelOwn => __('Cancel own pending deposits'),
            self::DepositApprove => __('Approve or reject deposits'),
            self::DepositApproveOwn => __('Approve own deposits'),
            self::DepositReverse => __('Reverse approved deposits'),
            self::LedgerViewBalance => __('View the fund balance'),
            self::LedgerViewTransactions => __('View ledger transactions'),
            self::LedgerAdjust => __('Post manual adjustments'),
            self::LedgerReimburse => __('Reimburse buyer payables'),
            self::ReportViewOwn => __('View own monthly report'),
            self::ReportViewAll => __('View every member in monthly reports'),
            self::ReportCloseMonth => __('Close and reopen months'),
            self::UserView => __('View users'),
            self::UserCreate => __('Create users'),
            self::UserUpdate => __('Edit users and assign roles'),
            self::UserDeactivate => __('Deactivate and reactivate users'),
            self::RoleView => __('View roles'),
            self::RoleManage => __('Create, edit and delete roles'),
            self::MealViewOwn => __('View own meals'),
            self::MealViewAll => __('View every member\'s meals'),
            self::MealRecord => __('Record daily meals for members'),
            self::SettingsView => __('View mess settings'),
            self::SettingsUpdate => __('Change mess settings'),
            self::SettingsResetData => __('Erase all financial and meal data and start from zero'),
            self::AuditView => __('View the audit log'),
        };
    }

    /**
     * A heading for a catalog resource in the role editor.
     */
    public static function groupLabel(string $group): string
    {
        return match ($group) {
            'member' => __('Members'),
            'grocery' => __('Grocery purchases'),
            'deposit' => __('Deposits'),
            'ledger' => __('Ledger'),
            'meal' => __('Meals'),
            'report' => __('Reports'),
            'user' => __('Users'),
            'role' => __('Roles and permissions'),
            'settings' => __('Settings'),
            'audit' => __('Audit log'),
            default => ucfirst($group),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
