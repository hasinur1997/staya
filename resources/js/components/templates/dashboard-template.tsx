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
    /** Two to four StatCards. */
    stats?: ReactNode[];
    /** What needs doing: recent purchases, the per-member table, queues. */
    children: ReactNode;
    /** Secondary content; moves below main on tablet and mobile. */
    aside?: ReactNode;
};

export function DashboardTemplate({ header, stats, children, aside }: Props) {
    return (
        <PageStack>
            <PageHeader {...header} />
            {stats && stats.length > 0 && <StatGrid stats={stats} />}
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
