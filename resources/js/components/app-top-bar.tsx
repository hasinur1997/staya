import { Link, usePage } from '@inertiajs/react';
import { Bell, Menu } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
import { AppearanceMenu } from '@/components/appearance-menu';
import { LanguageMenu } from '@/components/language-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbEllipsis,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SheetTrigger } from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCan } from '@/hooks/use-can';
import { useInitials } from '@/hooks/use-initials';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { appNavigation, navCount, visibleNavigation } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

function AppBreadcrumbs({
    breadcrumbs,
    collapse,
}: {
    breadcrumbs: BreadcrumbItemType[];
    collapse: boolean;
}) {
    // Tablet keeps the last two levels; earlier ones fold into "…".
    const __ = useTranslate();
    const hidden = collapse ? Math.max(breadcrumbs.length - 2, 0) : 0;
    const shown = breadcrumbs.slice(hidden);

    return (
        <Breadcrumb className="min-w-0">
            <BreadcrumbList className="flex-nowrap text-caption text-ink-muted">
                {hidden > 0 && (
                    <>
                        <BreadcrumbItem>
                            <BreadcrumbEllipsis className="size-6" />
                        </BreadcrumbItem>
                        <BreadcrumbSeparator />
                    </>
                )}
                {shown.map((item, index) => {
                    const isLast = index === shown.length - 1;

                    return (
                        <Fragment key={`${item.title}-${index}`}>
                            <BreadcrumbItem className="min-w-0">
                                {isLast ? (
                                    <BreadcrumbPage className="truncate text-ink">
                                        {__(item.title)}
                                    </BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink asChild>
                                        <Link
                                            href={item.href}
                                            className="truncate hover:text-ink"
                                        >
                                            {__(item.title)}
                                        </Link>
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                            {!isLast && <BreadcrumbSeparator />}
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}

/**
 * "Waiting for you": counts already shared for the sidebar, gathered in one
 * menu. There is no notifications store in v1 (docs/layout.md, question 1).
 */
function WaitingMenu() {
    const __ = useTranslate();
    const can = useCan();
    const { counts } = usePage().props.nav;
    const items = visibleNavigation(appNavigation, can)
        .flatMap((section) => section.items)
        .map((item) => ({ item, count: navCount(counts, item.count) }))
        .filter(({ count }) => count !== null);

    if (items.length === 0) {
        return null;
    }

    const total = items.reduce((sum, { count }) => sum + (count ?? 0), 0);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={`${__('Waiting for you')}, ${total}`}
                >
                    <Bell className="size-5" aria-hidden />
                    <Badge
                        variant="count"
                        className="absolute top-0.5 right-0.5 px-1.5"
                        aria-hidden
                    >
                        {total}
                    </Badge>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel>{__('Waiting for you')}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {items.map(({ item, count }) => (
                    <DropdownMenuItem key={item.title} asChild>
                        <Link
                            href={item.href}
                            className="flex w-full cursor-pointer items-center justify-between"
                        >
                            <span className="flex items-center gap-2">
                                <item.icon aria-hidden />
                                {__(item.title)}
                            </span>
                            <Badge variant="count">{count}</Badge>
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function UserMenu() {
    const { user } = usePage().props.auth;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="rounded-full"
                    aria-label="Open user menu"
                >
                    <Avatar className="size-8">
                        <AvatarImage src={user.avatar} alt="" />
                        <AvatarFallback className="bg-primary-soft text-caption text-ink">
                            {getInitials(user.name)}
                        </AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                <UserMenuContent user={user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppTopBar({
    breadcrumbs,
    title,
    collapseBreadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
    title?: string;
    collapseBreadcrumbs: boolean;
}) {
    const __ = useTranslate();
    const { balance } = usePage().props.fund;
    const [scrolled, setScrolled] = useState(false);
    const mobileTitle = title ?? breadcrumbs.at(-1)?.title;

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 0);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <header
            className={cn(
                'sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b bg-background/80 px-4 backdrop-blur transition-[border-color] md:h-16 md:gap-4 md:px-6 print:hidden',
                scrolled ? 'border-border' : 'border-transparent',
            )}
        >
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="-ml-2 lg:hidden"
                    aria-label="Open navigation"
                >
                    <Menu className="size-5" aria-hidden />
                </Button>
            </SheetTrigger>

            <div className="min-w-0 flex-1">
                {mobileTitle && (
                    <span className="block truncate text-title text-ink md:hidden">
                        {__(mobileTitle)}
                    </span>
                )}
                <div className="hidden md:block">
                    <AppBreadcrumbs
                        breadcrumbs={breadcrumbs}
                        collapse={collapseBreadcrumbs}
                    />
                </div>
            </div>

            {balance !== null && (
                <p className="hidden items-baseline gap-2 whitespace-nowrap md:flex">
                    <span className="stat-label">{__('Fund')}</span>
                    <span className="money text-body-strong">
                        {formatMoney(balance)}
                    </span>
                </p>
            )}

            <div className="flex items-center gap-1">
                <WaitingMenu />
                <LanguageMenu />
                <AppearanceMenu />
                <UserMenu />
            </div>
        </header>
    );
}
