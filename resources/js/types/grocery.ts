export type GroceryUnitValue = 'kg' | 'gm' | 'liter' | 'ml' | 'pcs' | 'dozen';

export type GroceryPaidByValue = 'fund' | 'member';

export type PurchaseRow = {
    id: number;
    /** Y-m-d business date (Asia/Dhaka). */
    purchase_date: string;
    buyer_member_id: number;
    buyer_name: string;
    /** Decimal string computed by the server, e.g. "350.00". */
    grand_total: string;
    /** Who paid the shop. */
    paid_by: GroceryPaidByValue;
    paid_by_member_id: number | null;
    /** "Fund", or the name of the member who paid. */
    paid_by_name: string;
    item_count: number;
    /** The first few item names, for list rows. */
    item_names: string[];
    status: 'active' | 'voided';
    status_label: string;
    /** The viewer recorded this purchase. */
    is_own: boolean;
    /** The viewer's edit window has closed and they cannot change it. */
    locked: boolean;
    /** ISO 8601 (UTC) while the viewer's own edit window is running. */
    window_closes_at: string | null;
    can: { update: boolean; delete: boolean };
};

export type PurchaseItem = {
    id: number;
    item_name: string;
    /** Decimal string with three places, e.g. "2.500". */
    quantity: string;
    unit: GroceryUnitValue;
    unit_label: string;
    unit_price: string;
    line_total: string;
};

export type PurchaseDetail = PurchaseRow & {
    items: PurchaseItem[];
    notes: string | null;
    has_receipt: boolean;
    /** What the fund paid; the rest of the total is the payable. */
    fund_paid: string;
    void_reason: string | null;
    /** Whether the viewer must give a reason to delete it. */
    void_needs_reason: boolean;
    created_by: string;
    /** ISO 8601, UTC. */
    created_at: string;
    updated_by: string | null;
    updated_at: string | null;
    voided_by: string | null;
    voided_at: string | null;
    payable: {
        id: number;
        /** The member the money is owed to. */
        member_id: number;
        member_name: string;
        amount: string;
        amount_reimbursed: string;
        outstanding: string;
    } | null;
};

export type PurchaseFilters = {
    /** YYYY-MM; wins over the date range. */
    month: string | null;
    from: string | null;
    to: string | null;
    buyer: number | null;
};

export type PayableRow = {
    id: number;
    grocery_purchase_id: number;
    purchase_date: string;
    member_id: number;
    member_name: string;
    /** A member paid in full, or the fund fell short. */
    paid_by: GroceryPaidByValue;
    amount: string;
    amount_reimbursed: string;
    outstanding: string;
    can: { reimburse: boolean };
};

/** Server-computed totals for a purchase form still being filled in. */
export type PurchaseTotals = {
    /** One per line, in order; null while the line is incomplete. */
    line_totals: (string | null)[];
    grand_total: string;
};
