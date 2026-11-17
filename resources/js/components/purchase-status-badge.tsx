import { Clock, Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatCountdown, useCountdown } from '@/hooks/use-countdown';
import { useTranslate } from '@/lib/i18n';
import type { PurchaseRow } from '@/types';

/**
 * Where a purchase stands for the viewer: Voided, Locked once their edit
 * window has closed, or a countdown while it is still open. Nothing shows
 * for an ordinary purchase. The server decides; this only mirrors it.
 */
export function PurchaseStatusBadge({
    purchase,
    serverNow,
}: {
    purchase: Pick<PurchaseRow, 'status' | 'locked' | 'window_closes_at'>;
    /** The server's time when the page was built (ISO 8601). */
    serverNow: string;
}) {
    const __ = useTranslate();
    const remaining = useCountdown(purchase.window_closes_at, serverNow);

    if (purchase.status === 'voided') {
        return <Badge variant="neutral">{__('Voided')}</Badge>;
    }

    if (purchase.locked || remaining === 0) {
        return (
            <Badge variant="neutral">
                <Lock aria-hidden />
                {__('Locked')}
            </Badge>
        );
    }

    if (remaining === null) {
        return null;
    }

    return (
        <Badge variant="warning" className="tabular-nums">
            <Clock aria-hidden />
            {__('Editable for :time', { time: formatCountdown(remaining) })}
        </Badge>
    );
}
