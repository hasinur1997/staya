import type { ReactNode } from 'react';

/**
 * One label and value in a `<dl>`. The label is small and muted, the value is
 * body text, and both sit on the same baseline on wide screens.
 */
export function DetailRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1 md:grid md:grid-cols-3 md:items-baseline md:gap-4">
            <dt className="text-caption text-ink-muted">{label}</dt>
            <dd className="text-body text-ink md:col-span-2">{children}</dd>
        </div>
    );
}
