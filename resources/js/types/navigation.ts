import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

export type NavCountKey = 'pendingApprovals' | 'openPayables';

export type AppNavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    /** Shown when the user holds any of these. Empty means every signed-in user. */
    permissions: string[];
    count?: NavCountKey;
    /** Accessible wording for the count, e.g. "waiting". */
    countLabel?: string;
};

export type AppNavSection = {
    id: string;
    /** Null for the first, unlabelled group. */
    title: string | null;
    collapsible?: boolean;
    items: AppNavItem[];
};

export type NavProps = {
    counts: Record<NavCountKey, number | null>;
};

export type MessProps = {
    /** Minutes a member may edit or void their own grocery purchase. */
    editWindowMinutes: number;
    maxUploadKb: number;
};

export type FundProps = {
    /** Decimal string such as "12450.00", or null when hidden. */
    balance: string | null;
};
