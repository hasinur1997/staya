export type LedgerEntryTypeValue =
    | 'opening_balance'
    | 'deposit'
    | 'grocery_expense'
    | 'reversal'
    | 'adjustment'
    | 'reimbursement';

export type LedgerDirectionValue = 'credit' | 'debit';

export type Option<T extends string = string> = {
    value: T;
    label: string;
};

export type LedgerEntryRow = {
    id: number;
    /** Y-m-d business date (Asia/Dhaka). */
    entry_date: string;
    type: LedgerEntryTypeValue;
    type_label: string;
    direction: LedgerDirectionValue;
    /** Decimal string, always positive. */
    amount: string;
    /** Decimal string, negative for a debit. */
    signed_amount: string;
    reason: string | null;
    source_type: string | null;
    source_id: number | null;
    reverses_entry_id: number | null;
    reversed_by_entry_id: number | null;
    created_by: string | null;
    /** ISO 8601, UTC. */
    created_at: string;
};

/** Server-computed decimal strings for a period. */
export type LedgerSummary = {
    opening: string;
    deposits: string;
    expenses: string;
    adjustments: string;
    reimbursements: string;
    closing: string;
};

export type LedgerFilters = {
    from: string;
    to: string;
    type: LedgerEntryTypeValue | null;
    direction: LedgerDirectionValue | null;
};

/** Laravel's length-aware paginator, as serialized by Inertia. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
