import { Badge } from '@/components/ui/badge';
import { useTranslate } from '@/lib/i18n';
import type { DepositRow, DepositStatusValue } from '@/types';

const variants = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
    cancelled: 'neutral',
    reversed: 'neutral',
} as const;

/** The status word on a tinted badge, plus a flag for self-approvals. */
export function DepositStatusBadge({
    status,
    label,
    selfApproved = false,
}: {
    status: DepositStatusValue;
    label: string;
    selfApproved?: boolean;
}) {
    const __ = useTranslate();

    return (
        <span className="inline-flex flex-wrap gap-1">
            <Badge variant={variants[status]}>{label}</Badge>
            {selfApproved && (
                <Badge variant="warning">{__('Self-approved')}</Badge>
            )}
        </span>
    );
}

/** The reason the member needs to see (rejection or reversal), if any. */
export function depositNote(deposit: DepositRow): string | null {
    if (deposit.status === 'rejected') {
        return deposit.rejection_reason;
    }

    return deposit.status === 'reversed' ? deposit.reversal_reason : null;
}
