import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';

/** A headline figure for dashboards and reports. `value` arrives formatted. */
export function StatCard({
    label,
    value,
    hint,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
}) {
    return (
        <Card className="h-full gap-1 px-4">
            <p className="text-small text-ink-muted">{label}</p>
            <p className="text-money text-ink">{value}</p>
            {hint && <p className="text-caption text-ink-muted">{hint}</p>}
        </Card>
    );
}
