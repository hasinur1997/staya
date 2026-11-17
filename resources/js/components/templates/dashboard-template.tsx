import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import type { PageHeaderProps } from '@/components/page-header';
import {
    PageGrid,
    PageStack,
    RegionStack,
    StatGrid,
    span,
} from '@/components/templates/page-grid';

type Props = {
    header: PageHeaderProps;
    /** Organisation-wide headline cards, shown above everything else. */
    overview?: ReactNode[];
    /** Chart cards, between the headline cards and the lists. */
    charts?: ReactNode;
    /** The member's own account, shown first and largest: three StatCards. */
    glance?: ReactNode[];
    /** Two to four StatCards. */
    stats?: ReactNode[];
    /** What needs doing: recent purchases, the per-member table, queues. */
    children: ReactNode;
    /** Secondary content; moves below main on tablet and mobile. */
    aside?: ReactNode;
};

export function DashboardTemplate({
    header,
    overview,
    charts,
    glance,
    stats,
    children,
    aside,
}: Props) {
    return (
        <PageStack>
            <PageHeader {...header} />
            {overview && overview.length > 0 && <StatGrid stats={overview} />}
            {glance && glance.length > 0 && (
                <PageGrid>
                    {glance.map((card, index) => (
                        <div key={index} className={span.aside}>
                            {card}
                        </div>
                    ))}
                </PageGrid>
            )}
            {stats && stats.length > 0 && <StatGrid stats={stats} />}
            {charts}
            <PageGrid>
                <RegionStack className={aside ? span.main : span.full}>
                    {children}
                </RegionStack>
                {aside && (
                    <RegionStack className={span.aside}>{aside}</RegionStack>
                )}
            </PageGrid>
        </PageStack>
    );
}
