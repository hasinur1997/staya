import { Badge } from '@/components/ui/badge';
import type { MemberStatusValue } from '@/types';

const variants = {
    active: 'success',
    inactive: 'neutral',
    pending: 'warning',
    rejected: 'error',
} as const;

/** The status word on a tinted badge; colour never carries it alone. */
export function MemberStatusBadge({
    status,
    label,
}: {
    status: MemberStatusValue;
    label: string;
}) {
    return <Badge variant={variants[status]}>{label}</Badge>;
}
