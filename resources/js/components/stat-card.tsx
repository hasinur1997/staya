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
            <p className="stat-label">{label}</p>
            <p className="stat-value">{value}</p>
            {hint && <p className="stat-meta">{hint}</p>}
        </Card>
    );
}
