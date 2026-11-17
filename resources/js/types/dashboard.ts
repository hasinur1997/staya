import type { DepositRow } from '@/types/deposit';
import type { PurchaseRow } from '@/types/grocery';

/** One member's figures for the month. Money is a decimal string. */
export type MonthlyMemberRow = {
    member_id: number;
    name: string;
    deposits: string;
    meals: number;
    share: string;
    /** Deposits minus share: positive is credit, negative is due. */
    due_credit: string;
};

export type MonthlyFigures = {
    deposits: string;
    expenses: string;
    split: 'equal' | 'meals';
    total_meals: number;
    active_members: number;
    share: string;
    /** Expenses the truncated shares leave uncovered. */
    leftover: string;
    members: MonthlyMemberRow[];
};

/** Figures behind the charts; null without `report.view_all`. */
export type DashboardInsights = {
    /** Six months ending with the chosen one. */
    trend: {
        month: string;
        deposits: string;
        expenses: string;
        /** Fund balance at the end of the month. */
        fund_end: string;
    }[];
    /** Against the month before; a leading minus means down. */
    changes: { deposits: string; expenses: string; fund: string };
    daily: { day: number; expenses: string; meals: number }[];
    top_items: { name: string; total: string }[];
};

export type OwnTrendRow = {
    month: string;
    deposits: string;
    share: string;
    due_credit: string;
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
    insights: DashboardInsights | null;
    /** The viewer's own deposits and share by month; null without a member profile or report access. */
    myTrend: OwnTrendRow[] | null;
    /** The viewer's own totals; null without a member profile. */
    account: {
        /** Approved deposits; null without access to deposits. */
        deposited: { total: string; month: string } | null;
        /** Grocery bought by the viewer, voided ones excluded. */
        bajar: { total: string; month: string } | null;
    } | null;
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
