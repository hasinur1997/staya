import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ActionButton } from '@/components/action-button';
import { PageHeader } from '@/components/page-header';
import type { PageHeaderProps } from '@/components/page-header';
import { StickyActionBar } from '@/components/sticky-action-bar';
import {
    PageGrid,
    PageStack,
    RegionStack,
} from '@/components/templates/page-grid';
import { cn } from '@/lib/utils';

type Props = {
    /** Title and description only; the form's submit is the primary action. */
    header: Omit<PageHeaderProps, 'primaryAction' | 'mobileAction'>;
    /** Id of the <form> in `children`; the submit buttons target it. */
    formId: string;
    submitLabel: string;
    processing?: boolean;
    cancelHref: NonNullable<InertiaLinkProps['href']>;
    /** The form with its fieldset cards. */
    children: ReactNode;
    /** Live summary or help; sits between the form and the actions on tablet. */
    aside?: ReactNode;
    /** Error summary shown above the actions. */
    errors?: ReactNode;
};

export function FormTemplate({
    header,
    formId,
    submitLabel,
    processing = false,
    cancelHref,
    children,
    aside,
    errors,
}: Props) {
    const submit = { label: submitLabel, form: formId, disabled: processing };
    const cancel = { label: 'Cancel', href: cancelHref };

    return (
        <PageStack>
            <PageHeader {...header} mobileAction={cancel} />
            <PageGrid>
                <RegionStack className="col-span-4 md:col-span-8 lg:col-span-8 lg:row-start-1">
                    {children}
                </RegionStack>
                {aside && (
                    <RegionStack className="col-span-4 md:col-span-8 lg:col-span-4 lg:col-start-9 lg:row-span-2 lg:row-start-1">
                        {aside}
                    </RegionStack>
                )}
                <div
                    className={cn(
                        'col-span-4 flex flex-col gap-4 md:col-span-8 lg:col-span-8',
                        aside && 'lg:row-start-2',
                    )}
                >
                    {errors}
                    <div className="hidden items-center justify-end gap-2 md:flex print:hidden">
                        <ActionButton action={cancel} variant="outline" />
                        <ActionButton action={submit} />
                    </div>
                </div>
            </PageGrid>

            <StickyActionBar>
                <ActionButton action={submit} size="lg" className="w-full" />
            </StickyActionBar>
        </PageStack>
    );
}
