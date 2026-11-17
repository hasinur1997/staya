import { Link } from '@inertiajs/react';
import { EllipsisVertical } from 'lucide-react';
import type { ReactNode } from 'react';
import { ActionButton } from '@/components/action-button';
import { StickyActionBar } from '@/components/sticky-action-bar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PageAction } from '@/types';

export type PageHeaderProps = {
    title: string;
    overline?: string;
    description?: ReactNode;
    /** A status Badge shown next to the title. */
    status?: ReactNode;
    /** The view's one primary action. Becomes the sticky bar on mobile. */
    primaryAction?: PageAction;
    /** Up to two show as buttons on tablet+; the rest join the menu. */
    secondaryActions?: PageAction[];
    /** Always in the "More" menu (destructive actions belong here). */
    menuActions?: PageAction[];
    /** Mobile only, shown as a ghost button (e.g. a form's Cancel). */
    mobileAction?: PageAction;
    /** Tabs or a filter row under the title. */
    children?: ReactNode;
};

export function MoreMenu({
    actions,
    className,
}: {
    actions: PageAction[];
    className?: string;
}) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={className}
                    aria-label="More actions"
                >
                    <EllipsisVertical className="size-5" aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-48">
                {actions.map((action) => (
                    <DropdownMenuItem
                        key={action.label}
                        variant={action.destructive ? 'destructive' : 'default'}
                        disabled={action.disabled}
                        asChild={Boolean(action.href)}
                        onSelect={action.onClick}
                    >
                        {action.href ? (
                            <Link href={action.href} className="w-full">
                                {action.icon && <action.icon aria-hidden />}
                                {action.label}
                            </Link>
                        ) : (
                            <>
                                {action.icon && <action.icon aria-hidden />}
                                {action.label}
                            </>
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function PageHeader({
    title,
    overline,
    description,
    status,
    primaryAction,
    secondaryActions = [],
    menuActions = [],
    mobileAction,
    children,
}: PageHeaderProps) {
    const shownSecondary = secondaryActions.slice(0, 2);
    const desktopMenu = [...secondaryActions.slice(2), ...menuActions];
    const mobileMenu = [...secondaryActions, ...menuActions];

    return (
        <header className="flex flex-col gap-4">
            <div className="flex items-start gap-4">
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    {overline && (
                        <p className="text-overline text-neutral">{overline}</p>
                    )}
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 className="text-heading text-ink">{title}</h1>
                        {status}
                    </div>
                    {description && (
                        <div className="text-small text-ink-muted">
                            {description}
                        </div>
                    )}
                </div>

                <div className="flex shrink-0 items-center gap-2 print:hidden">
                    {mobileAction && (
                        <ActionButton
                            action={mobileAction}
                            variant="ghost"
                            className="md:hidden"
                        />
                    )}
                    <MoreMenu actions={mobileMenu} className="md:hidden" />

                    {shownSecondary.map((action) => (
                        <ActionButton
                            key={action.label}
                            action={action}
                            variant="outline"
                            className="hidden md:inline-flex"
                        />
                    ))}
                    <MoreMenu
                        actions={desktopMenu}
                        className="hidden md:inline-flex"
                    />
                    {primaryAction && (
                        <ActionButton
                            action={primaryAction}
                            className="hidden md:inline-flex"
                        />
                    )}
                </div>
            </div>

            {children}

            {primaryAction && (
                <StickyActionBar>
                    <ActionButton
                        action={primaryAction}
                        size="lg"
                        className="w-full"
                    />
                </StickyActionBar>
            )}
        </header>
    );
}
