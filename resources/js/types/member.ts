export type MemberStatusValue = 'pending' | 'rejected' | 'active' | 'inactive';

export type MemberRow = {
    id: number;
    name: string;
    email: string;
    phone: string;
    status: MemberStatusValue;
    status_label: string;
    /** Y-m-d business date of the latest stay. */
    joined_on: string | null;
    left_on: string | null;
};

export type MemberStayRow = {
    id: number;
    /** Y-m-d business date (Asia/Dhaka). */
    joined_on: string;
    left_on: string | null;
};

/** Personal details; never includes the NID number. */
export type MemberProfile = {
    id: number;
    user_id: number;
    name: string;
    first_name: string;
    last_name: string;
    name_bn: string;
    email: string;
    phone: string;
    father_name: string;
    father_name_bn: string;
    mother_name: string;
    mother_name_bn: string;
    permanent_address: string;
    status: MemberStatusValue;
    status_label: string;
    rejection_reason: string | null;
    reviewed_by: string | null;
    /** ISO 8601, UTC. */
    reviewed_at: string | null;
    registered_at: string | null;
    has_photo: boolean;
    has_banner: boolean;
    /** Changes when the member is updated, to refresh cached pictures. */
    media_version: number | null;
    /** null when the viewer may not see NID data. */
    has_nid_file: boolean | null;
    stays: MemberStayRow[];
};

export type MemberFilters = {
    status: MemberStatusValue | null;
    search: string;
};

export type LinkableUser = {
    id: number;
    name: string;
    email: string;
    /** The signed-in user's own account. */
    is_self: boolean;
};

export type RegistrationRow = {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    phone: string;
    registered_at: string | null;
};

export type MemberTab =
    | 'profile'
    | 'meals'
    | 'purchases'
    | 'deposits'
    | 'balance';

export type MemberMealDay = {
    date: string;
    weekday: number;
    counts: Record<string, number>;
    total: number;
};

/** One member's meals for a month. */
export type MemberMeals = {
    month: string;
    currentMonth: string;
    types: { id: number; name: string }[];
    days: MemberMealDay[];
    typeTotals: Record<string, number>;
    total: number;
    figures: {
        expenses: string;
        total_meals: number;
        rate: string | null;
        cost: string | null;
    };
    can: { record: boolean };
};

export type MemberBalance = {
    /** Sum of the months the member owes. */
    to_pay: string;
    /** Sum of the months they deposited more than their share. */
    credit: string;
    /** Open payables: what the fund still owes them. */
    fund_owes: string;
    months: {
        month: string;
        deposits: string;
        share: string;
        meals: number;
        due_credit: string;
    }[];
};
