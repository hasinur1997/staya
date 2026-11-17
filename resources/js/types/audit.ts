export type AuditRow = {
    id: number;
    /** ISO 8601, UTC; shown in Asia/Dhaka. */
    created_at: string | null;
    log_name: string;
    /** The stored action, e.g. "deposit_approved". */
    action: string;
    action_label: string;
    actor_id: number | null;
    /** Null for events with no signed-in user, such as a failed login. */
    actor_name: string | null;
    subject_type: string | null;
    subject_type_label: string | null;
    subject_id: number | null;
    ip_address: string | null;
    /** The email typed at a failed or blocked sign-in. */
    email_tried: string | null;
    has_changes: boolean;
    /** An approval by the person who submitted the deposit. */
    self_approved: boolean;
};

export type AuditDiffRow = {
    field: string;
    old: string | null;
    new: string | null;
};

export type AuditDetail = AuditRow & {
    diff: AuditDiffRow[];
    subject_url: string | null;
    properties: Record<string, unknown>;
};

export type AuditFilters = {
    from: string;
    to: string;
    actor: number | null;
    log: string | null;
    action: string | null;
    subject: string | null;
    subject_id: number | null;
};
