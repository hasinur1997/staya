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
    email: string;
    phone: string;
    father_name: string;
    mother_name: string;
    permanent_address: string;
    status: MemberStatusValue;
    status_label: string;
    rejection_reason: string | null;
    reviewed_by: string | null;
    /** ISO 8601, UTC. */
    reviewed_at: string | null;
    registered_at: string | null;
    has_photo: boolean;
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
};

export type RegistrationRow = {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    phone: string;
    registered_at: string | null;
};
