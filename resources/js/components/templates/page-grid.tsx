import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Column spans on the 4 / 8 / 12-column grid (mobile / tablet / desktop). */
export const span = {
    full: 'col-span-4 md:col-span-8 lg:col-span-12',
    main: 'col-span-4 md:col-span-8 lg:col-span-8',
    aside: 'col-span-4 md:col-span-8 lg:col-span-4',
    stat: 'col-span-2 md:col-span-4 lg:col-span-3',
} as const;

export function PageGrid({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'grid grid-cols-4 gap-4 md:grid-cols-8 md:gap-6 lg:grid-cols-12',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** Vertical rhythm between page sections: 24 / 32 / 48px. */
export function PageStack({ children }: { children: ReactNode }) {
    return (
        <div className="flex flex-col gap-6 md:gap-8 lg:gap-12">{children}</div>
    );
}

/** A column of cards inside a region. */
export function RegionStack({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('flex min-w-0 flex-col gap-4 md:gap-6', className)}>
            {children}
        </div>
    );
}

export function StatGrid({ stats }: { stats: ReactNode[] }) {
    return (
        <PageGrid>
            {stats.map((stat, index) => (
                <div key={index} className={span.stat}>
                    {stat}
                </div>
            ))}
        </PageGrid>
    );
}
