import type { DepositRow } from '@/types/deposit';
import type { PurchaseRow } from '@/types/grocery';

/** One member's figures for the month. Money is a decimal string. */
export type MonthlyMemberRow = {
    member_id: number;
    name: string;
    deposits: string;
    share: string;
    /** Deposits minus share: positive is credit, negative is due. */
    due_credit: string;
};

export type MonthlyFigures = {
    deposits: string;
    expenses: string;
    active_members: number;
    share: string;
    /** Expenses the truncated shares leave uncovered. */
    leftover: string;
    members: MonthlyMemberRow[];
};

export type DashboardQueues = {
    pendingDeposits: number | null;
    pendingRegistrations: number | null;
    openPayables: number | null;
};

export type DashboardProps = {
    /** YYYY-MM shown on the page. */
    month: string;
    currentMonth: string;
    serverNow: string;
    memberStatus: 'active' | 'inactive' | null;
    /** The viewer's own row; null without a member profile or report access. */
    summary: MonthlyMemberRow | null;
    /** Everyone's figures; null without `report.view_all`. */
    report: MonthlyFigures | null;
    /** What the fund still owes the viewer; null without a member profile. */
    owed: string | null;
    recentDeposits: DepositRow[];
    recentPurchases: PurchaseRow[];
    queues: DashboardQueues;
    can: {
        createDeposit: boolean;
        createPurchase: boolean;
        viewPurchases: boolean;
        viewDeposits: boolean;
    };
};
