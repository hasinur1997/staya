export type InvitationStatusValue = 'pending' | 'used' | 'revoked' | 'expired';

export type InvitationRow = {
    id: number;
    email: string;
    status: InvitationStatusValue;
    status_label: string;
    invited_by: string;
    /** ISO 8601, UTC; shown in Asia/Dhaka. */
    created_at: string | null;
    expires_at: string;
    used_at: string | null;
    member_id: number | null;
    member_name: string | null;
    can: { revoke: boolean; resend: boolean };
};
