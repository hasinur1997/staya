import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import type { PageHeaderProps } from '@/components/page-header';
import {
    PageGrid,
    PageStack,
    RegionStack,
    span,
} from '@/components/templates/page-grid';

type Props = {
    header: PageHeaderProps;
    /** Section cards: line items, details, history. */
    children: ReactNode;
    /** Summary card first, then related links and audit history. */
    aside?: ReactNode;
};

export function DetailTemplate({ header, children, aside }: Props) {
    return (
        <PageStack>
            <PageHeader {...header} />
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
