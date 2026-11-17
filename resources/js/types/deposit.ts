export type DepositStatusValue =
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'cancelled'
    | 'reversed';

export type DepositRow = {
    id: number;
    member_id: number;
    member_name: string;
    /** Decimal string, e.g. "2500.00". */
    amount: string;
    /** Y-m-d business date (Asia/Dhaka). */
    deposit_date: string;
    method: 'cash';
    method_label: string;
    status: DepositStatusValue;
    status_label: string;
    rejection_reason: string | null;
    reversal_reason: string | null;
    is_self_approved: boolean;
    /** The deposit belongs to the viewer's own member profile. */
    is_own: boolean;
    can: { cancel: boolean };
};

export type DepositDetail = DepositRow & {
    submitted_by: string;
    /** ISO 8601, UTC. */
    submitted_at: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    cancelled_at: string | null;
    reversed_by: string | null;
    reversed_at: string | null;
};

export type DepositFilters = {
    status: DepositStatusValue | null;
    member: number | null;
    /** YYYY-MM. */
    month: string | null;
};

export type MemberOption = {
    id: number;
    name: string;
    status: string;
};
