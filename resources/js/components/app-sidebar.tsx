import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import AppLogo from '@/components/app-logo';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { SheetContent, SheetTitle } from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { formatMoney } from '@/lib/money';
import { appNavigation, navCount, visibleNavigation } from '@/lib/navigation';
import { cn, toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { AppNavItem, AppNavSection } from '@/types';

type Variant = 'full' | 'rail';

const SECTION_STORAGE_KEY = 'staya.nav.collapsed';

function readCollapsed(): string[] {
    try {
        const value = JSON.parse(
            window.localStorage.getItem(SECTION_STORAGE_KEY) ?? '[]',
        );

        return Array.isArray(value) ? value : [];
    } catch {
        return [];
    }
}

function writeCollapsed(ids: string[]) {
    try {
        window.localStorage.setItem(SECTION_STORAGE_KEY, JSON.stringify(ids));
    } catch {
        // Storage unavailable: the section simply opens again next visit.
    }
}

function useIsActive() {
    const { currentUrl } = useCurrentUrl();

    return (item: AppNavItem) => {
        const path = new URL(toUrl(item.href), 'http://localhost').pathname;

        return currentUrl === path || currentUrl.startsWith(`${path}/`);
    };
}

function accessibleName(item: AppNavItem, count: number | null): string {
    return count
        ? `${item.title}, ${count} ${item.countLabel ?? ''}`.trim()
        : item.title;
}

function NavLink({
    item,
    variant,
    active,
    count,
}: {
    item: AppNavItem;
    variant: Variant;
    active: boolean;
    count: number | null;
}) {
    const className = cn(
        'relative flex h-11 items-center rounded-md text-ink-muted transition-[color,background-color] duration-150 hover:bg-surface-raised hover:text-ink',
        // 3px primary bar on the active item.
        'before:absolute before:inset-y-2 before:left-0 before:w-0.75 before:rounded-full',
        active && 'bg-primary-soft text-ink before:bg-primary',
        variant === 'full' ? 'gap-3 px-3' : 'justify-center',
    );

    if (variant === 'rail') {
        return (
            <Tooltip>
                <TooltipTrigger asChild>
                    <Link
                        href={item.href}
                        prefetch
                        className={className}
                        aria-current={active ? 'page' : undefined}
                        aria-label={accessibleName(item, count)}
                    >
                        <item.icon className="size-5" aria-hidden />
                        {count && (
                            <Badge
                                variant="count"
                                className="absolute top-1 right-2 px-1.5"
                                aria-hidden
                            >
                                {count}
                            </Badge>
                        )}
                    </Link>
                </TooltipTrigger>
                <TooltipContent side="right">{item.title}</TooltipContent>
            </Tooltip>
        );
    }

    return (
        <Link
            href={item.href}
            prefetch
            className={cn(className, active && 'font-semibold')}
            aria-current={active ? 'page' : undefined}
            aria-label={count ? accessibleName(item, count) : undefined}
        >
            <item.icon className="size-5 shrink-0" aria-hidden />
            <span className="flex-1 truncate">{item.title}</span>
            {count && (
                <Badge variant="count" aria-hidden>
                    {count}
                </Badge>
            )}
        </Link>
    );
}

function NavItems({
    section,
    variant,
}: {
    section: AppNavSection;
    variant: Variant;
}) {
    const { counts } = usePage().props.nav;
    const isActive = useIsActive();

    return (
        <ul className="flex flex-col gap-1">
            {section.items.map((item) => (
                <li key={item.title}>
                    <NavLink
                        item={item}
                        variant={variant}
                        active={isActive(item)}
                        count={navCount(counts, item.count)}
                    />
                </li>
            ))}
        </ul>
    );
}

function CollapsibleSection({ section }: { section: AppNavSection }) {
    const isActive = useIsActive();
    const [collapsed, setCollapsed] = useState(() =>
        readCollapsed().includes(section.id),
    );
    // A section holding the current page never hides it.
    const open = !collapsed || section.items.some(isActive);

    const toggle = (next: boolean) => {
        setCollapsed(!next);
        const others = readCollapsed().filter((id) => id !== section.id);
        writeCollapsed(next ? others : [...others, section.id]);
    };

    return (
        <Collapsible open={open} onOpenChange={toggle}>
            <CollapsibleTrigger className="flex h-8 w-full items-center justify-between rounded-md px-3 text-overline text-neutral transition-[color,background-color] hover:bg-surface-raised">
                {section.title}
                <ChevronDown
                    className={cn(
                        'size-4 transition-transform duration-150',
                        !open && '-rotate-90',
                    )}
                    aria-hidden
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="pt-1">
                <NavItems section={section} variant="full" />
            </CollapsibleContent>
        </Collapsible>
    );
}

function SidebarNav({ variant }: { variant: Variant }) {
    const can = useCan();
    const sections = visibleNavigation(appNavigation, can);

    return (
        <nav
            aria-label="Main"
            className="flex flex-1 flex-col gap-4 overflow-y-auto px-3 py-4"
        >
            {sections.map((section, index) => {
                if (variant === 'rail') {
                    return (
                        <div key={section.id} className="flex flex-col gap-4">
                            {index > 0 && <Separator />}
                            <NavItems section={section} variant="rail" />
                        </div>
                    );
                }

                if (section.collapsible && section.title) {
                    return (
                        <CollapsibleSection
                            key={section.id}
                            section={section}
                        />
                    );
                }

                return (
                    <div
                        key={section.id}
                        role="group"
                        aria-labelledby={
                            section.title ? `nav-${section.id}` : undefined
                        }
                        className="flex flex-col gap-1"
                    >
                        {section.title && (
                            <h2
                                id={`nav-${section.id}`}
                                className="flex h-8 items-center px-3 text-overline text-neutral"
                            >
                                {section.title}
                            </h2>
                        )}
                        <NavItems section={section} variant="full" />
                    </div>
                );
            })}
        </nav>
    );
}

function SidebarLogo({ variant }: { variant: Variant }) {
    return (
        <Link
            href={dashboard()}
            className={cn(
                'flex h-16 shrink-0 items-center gap-3 px-6',
                variant === 'rail' && 'justify-center px-0',
            )}
        >
            <AppLogo showName={variant === 'full'} />
        </Link>
    );
}

/** The fixed sidebar: icon rail on tablet, full width on desktop. */
export function AppSidebar({ variant }: { variant: Variant }) {
    return (
        <aside
            className={cn(
                'fixed inset-y-0 left-0 z-30 hidden flex-col border-r bg-background md:flex print:hidden',
                variant === 'full' ? 'w-64' : 'w-18',
            )}
        >
            <SidebarLogo variant={variant} />
            <SidebarNav variant={variant} />
        </aside>
    );
}

/** The navigation drawer for mobile and tablet. Render inside a `Sheet`. */
export function AppDrawer() {
    const { balance } = usePage().props.fund;

    return (
        <SheetContent
            id="app-drawer"
            side="left"
            className="w-64 gap-0 p-0"
            aria-describedby={undefined}
        >
            <SheetTitle className="sr-only">Navigation</SheetTitle>
            <SidebarLogo variant="full" />
            <SidebarNav variant="full" />
            {balance !== null && (
                <div className="border-t px-6 py-4">
                    <p className="text-small text-ink-muted">Fund balance</p>
                    <p className="text-money text-ink">
                        {formatMoney(balance)}
                    </p>
                </div>
            )}
        </SheetContent>
    );
}
