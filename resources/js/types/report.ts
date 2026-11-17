import type { MonthlyMemberRow } from '@/types/dashboard';

export type DepositTotal = { count: number; amount: string };

export type ReportClosure = {
    status: 'open' | 'closed';
    /** ISO 8601, UTC. */
    closed_at: string | null;
    closed_by: string | null;
    reopened_at: string | null;
    reopened_by: string | null;
    reopen_reason: string | null;
};

export type Report = {
    /** Y-m-d business dates, inclusive. */
    from: string;
    to: string;
    /** YYYY-MM when the period is exactly one month. */
    month: string | null;
    figures: {
        deposits: string;
        expenses: string;
        split: 'equal' | 'meals';
        active_members: number;
        total_meals: number;
        /** The equal share, or the cost of one meal when split by meals. */
        share: string;
        leftover: string;
    };
    /** Null when the viewer may not see the fund balance. */
    ledger: {
        opening: string;
        closing: string;
        adjustments: string;
        reimbursements: string;
    } | null;
    members: MonthlyMemberRow[];
    /** Null without `report.view_all`. */
    unapproved: { pending: DepositTotal; rejected: DepositTotal } | null;
    self_approved:
        | {
              id: number;
              member_id: number;
              member_name: string;
              amount: string;
              deposit_date: string;
          }[]
        | null;
    open_payables: DepositTotal | null;
    closure: ReportClosure | null;
};

export type ReportFilters = {
    month: string | null;
    from: string | null;
    to: string | null;
    member: number | null;
};

/** One month of a member's own figures, for their profile. */
export type MemberMonth = {
    month: string;
    deposits: string;
    share: string;
    due_credit: string;
};
