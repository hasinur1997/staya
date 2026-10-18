import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import type { PageHeaderProps } from '@/components/page-header';
import { PageStack, StatGrid } from '@/components/templates/page-grid';

type Props = {
    header: PageHeaderProps;
    /** Month/year picker, range and member filter. */
    period?: ReactNode;
    /** Rows of StatCards (four per row on desktop). */
    summary?: ReactNode[];
    /** Full-width section cards, in print order. */
    children: ReactNode;
};

export function ReportTemplate({ header, period, summary, children }: Props) {
    return (
        <PageStack>
            <PageHeader {...header}>
                {header.children}
                {period && (
                    <div className="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center print:hidden">
                        {period}
                    </div>
                )}
            </PageHeader>
            {summary && summary.length > 0 && <StatGrid stats={summary} />}
            <div className="flex flex-col gap-4 md:gap-6">{children}</div>
        </PageStack>
    );
}
