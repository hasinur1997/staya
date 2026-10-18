<?php

namespace App\Enums;

/**
 * Sidebar screens that are not built yet. Each value is its URL path; the
 * page shows what is coming. Remove a case when its real routes ship.
 */
enum UpcomingFeature: string
{
    case Grocery = 'grocery';
    case Deposits = 'deposits';
    case Reports = 'reports';
    case Approvals = 'approvals';
    case Payables = 'payables';
    case AuditLog = 'audit-log';

    public function title(): string
    {
        return match ($this) {
            self::Grocery => __('Grocery purchases'),
            self::Deposits => __('Deposits'),
            self::Reports => __('Reports'),
            self::Approvals => __('Pending approvals'),
            self::Payables => __('Payables'),
            self::AuditLog => __('Audit log'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Grocery => __('Record multi-item grocery purchases with totals worked out for you.'),
            self::Deposits => __('Submit cash deposits and follow them from pending to approved.'),
            self::Reports => __('Monthly summaries of spending, deposits and each member’s due or credit.'),
            self::Approvals => __('Deposits waiting for a decision.'),
            self::Payables => __('Money the fund owes buyers, and the reimbursements that settle it.'),
            self::AuditLog => __('A read-only history of every financial and access change.'),
        };
    }

    /**
     * What the finished screen will let people do.
     *
     * @return list<string>
     */
    public function highlights(): array
    {
        return match ($this) {
            self::Grocery => [
                __('Add several items to one purchase; the server works out every total'),
                __('Edit your own purchase within 30 minutes of recording it'),
                __('Void a purchase with a reason instead of deleting it'),
            ],
            self::Deposits => [
                __('Submit a deposit for yourself or, with permission, for another member'),
                __('Pending and rejected deposits never touch the fund balance'),
                __('Each approval posts to the ledger exactly once'),
            ],
            self::Reports => [
                __('Totals for each month in Asia/Dhaka time'),
                __('Equal share, deposits and due or credit for every member'),
                __('Close a month so its figures can no longer change'),
            ],
            self::Approvals => [
                __('Approve or reject pending deposits with a reason'),
                __('Each approval posts to the ledger exactly once'),
                __('A count in the sidebar shows what is waiting'),
            ],
            self::Payables => [
                __('See what the fund owes each buyer after a large purchase'),
                __('Record reimbursements that never overdraw the fund'),
                __('Every reimbursement is posted to the ledger'),
            ],
            self::AuditLog => [
                __('Before and after values for every financial change'),
                __('Who made each change, when and from which IP address'),
                __('Filter by person, action and date'),
            ],
        };
    }

    /**
     * The permissions that show this screen; holding any one is enough.
     * Mirrors the sidebar in resources/js/lib/navigation.ts.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Grocery => [Permission::GroceryView],
            self::Deposits => [Permission::DepositViewOwn, Permission::DepositViewAll],
            self::Reports => [Permission::ReportViewOwn, Permission::ReportViewAll],
            // Registration reviewers already have their queue (ApprovalController).
            self::Approvals => [Permission::DepositApprove],
            self::Payables => [Permission::LedgerReimburse],
            self::AuditLog => [Permission::AuditView],
        };
    }
}
