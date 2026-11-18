export type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    /** ISO 8601, UTC. */
    last_login_at: string | null;
    roles: string[];
    can: { update: boolean };
};

export type EditableUser = {
    id: number;
    name: string;
    name_bn: string;
    email: string;
    is_active: boolean;
    last_login_at: string | null;
    role_ids: number[];
};

export type RoleOption = {
    id: number;
    name: string;
    is_system: boolean;
    /** The viewer holds every permission in this role. */
    assignable: boolean;
};

export type RoleRow = {
    id: number;
    name: string;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
    can: { update: boolean };
};

export type EditableRole = {
    id: number;
    name: string;
    is_system: boolean;
    users_count: number;
    permissions: string[];
};

export type PermissionOption = {
    name: string;
    description: string | null;
    /** The viewer holds it, so may add it to a role. */
    grantable: boolean;
};

export type PermissionGroup = {
    group: string;
    label: string;
    permissions: PermissionOption[];
};

export type MessSettings = {
    mess_name: string | null;
    mess_address: string | null;
    currency: string;
    timezone: string;
    /** Decimal string such as "0.00". */
    opening_balance: string;
    /** Y-m-d. */
    fund_start_date: string | null;
    grocery_edit_window_minutes: number;
    members_can_view_balance: boolean;
    max_upload_kb: number;
    cost_split: 'equal' | 'meals';
    /** Language for visitors who have not chosen one, e.g. "en" or "bn". */
    default_locale: string;
    /** ISO 8601, UTC. */
    updated_at: string | null;
    updated_by: string | null;
};
