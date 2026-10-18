import { SlidersHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import type { PageHeaderProps } from '@/components/page-header';
import { PageStack } from '@/components/templates/page-grid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useBreakpoint } from '@/hooks/use-breakpoint';

type Props = {
    header: PageHeaderProps;
    /** Always visible, e.g. the month picker. */
    filters?: ReactNode;
    /** Inline on tablet+, behind a "Filters" sheet on mobile. */
    moreFilters?: ReactNode;
    activeFilterCount?: number;
    /** e.g. "38 purchases". */
    resultCount?: ReactNode;
    /** One line of server-computed totals for the filtered set. */
    summary?: ReactNode;
    /** Usually a ResponsiveTable. */
    children: ReactNode;
    pagination?: ReactNode;
};

export function ListTemplate({
    header,
    filters,
    moreFilters,
    activeFilterCount = 0,
    resultCount,
    summary,
    children,
    pagination,
}: Props) {
    const isMobile = useBreakpoint() === 'mobile';
    const hasFilterBar = filters || moreFilters || resultCount;

    return (
        <PageStack>
            <PageHeader {...header}>
                {header.children}
                {hasFilterBar && (
                    <div className="flex flex-wrap items-center gap-3 print:hidden">
                        {filters}
                        {moreFilters &&
                            (isMobile ? (
                                <Sheet>
                                    <SheetTrigger asChild>
                                        <Button variant="outline">
                                            <SlidersHorizontal aria-hidden />
                                            Filters
                                            {activeFilterCount > 0 && (
                                                <Badge variant="count">
                                                    {activeFilterCount}
                                                    <span className="sr-only">
                                                        {' '}
                                                        active
                                                    </span>
                                                </Badge>
                                            )}
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent
                                        side="bottom"
                                        className="rounded-t-lg pb-6"
                                    >
                                        <SheetHeader>
                                            <SheetTitle className="text-subhead">
                                                Filters
                                            </SheetTitle>
                                            <SheetDescription>
                                                Narrow the list.
                                            </SheetDescription>
                                        </SheetHeader>
                                        <div className="flex flex-col gap-4 px-4">
                                            {moreFilters}
                                        </div>
                                    </SheetContent>
                                </Sheet>
                            ) : (
                                moreFilters
                            ))}
                        {resultCount && (
                            <span className="ml-auto text-small text-ink-muted">
                                {resultCount}
                            </span>
                        )}
                    </div>
                )}
            </PageHeader>

            <div className="flex flex-col gap-4">
                {summary && (
                    <p className="text-small text-ink-muted tabular-nums">
                        {summary}
                    </p>
                )}
                {children}
                {pagination && (
                    <div className="flex justify-center print:hidden">
                        {pagination}
                    </div>
                )}
            </div>
        </PageStack>
    );
}
