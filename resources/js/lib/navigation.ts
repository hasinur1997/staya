import {
    BookOpen,
    ChartColumn,
    ClipboardCheck,
    HandCoins,
    LayoutGrid,
    ScrollText,
    Settings,
    ShieldCheck,
    ShoppingBasket,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import { dashboard, upcoming } from '@/routes';
import { index as approvals } from '@/routes/approvals';
import { index as ledger } from '@/routes/ledger';
import { index as members } from '@/routes/members';
import { edit as messSettings } from '@/routes/mess-settings';
import { index as roles } from '@/routes/roles';
import { index as users } from '@/routes/users';
import type { AppNavSection, NavProps } from '@/types';

/*
 * The sidebar, from docs/layout.md (layer 2). Items declare the permissions
 * that show them; nothing here checks a role. Screens that are not built yet
 * link to the "coming soon" page (App\Enums\UpcomingFeature); switch each to
 * its own Wayfinder route when it ships.
 */
export const appNavigation: AppNavSection[] = [
    {
        id: 'main',
        title: null,
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
                permissions: [],
            },
            {
                title: 'Grocery purchases',
                href: upcoming('grocery'),
                icon: ShoppingBasket,
                permissions: ['grocery.view'],
            },
            {
                title: 'Deposits',
                href: upcoming('deposits'),
                icon: Wallet,
                permissions: ['deposit.view_own', 'deposit.view_all'],
            },
            {
                title: 'Reports',
                href: upcoming('reports'),
                icon: ChartColumn,
                permissions: ['report.view_own', 'report.view_all'],
            },
        ],
    },
    {
        id: 'manage',
        title: 'Manage',
        items: [
            {
                title: 'Members',
                href: members(),
                icon: Users,
                permissions: ['member.view'],
            },
            {
                title: 'Pending approvals',
                href: approvals(),
                icon: ClipboardCheck,
                permissions: ['deposit.approve', 'member.approve_registration'],
                count: 'pendingApprovals',
                countLabel: 'waiting',
            },
            {
                title: 'Ledger',
                href: ledger(),
                icon: BookOpen,
                permissions: ['ledger.view_transactions'],
            },
            {
                title: 'Payables',
                href: upcoming('payables'),
                icon: HandCoins,
                permissions: ['ledger.reimburse'],
                count: 'openPayables',
                countLabel: 'open',
            },
        ],
    },
    {
        id: 'admin',
        title: 'Admin',
        collapsible: true,
        items: [
            {
                title: 'Users',
                href: users(),
                icon: UserCog,
                permissions: ['user.view'],
            },
            {
                title: 'Roles',
                href: roles(),
                icon: ShieldCheck,
                permissions: ['role.view'],
            },
            {
                title: 'Settings',
                href: messSettings(),
                icon: Settings,
                permissions: ['settings.view'],
            },
            {
                title: 'Audit log',
                href: upcoming('audit-log'),
                icon: ScrollText,
                permissions: ['audit.view'],
            },
        ],
    },
];

/** Drops items the user can't see, then sections left empty. */
export function visibleNavigation(
    sections: AppNavSection[],
    can: (...permissions: string[]) => boolean,
): AppNavSection[] {
    return sections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => can(...item.permissions)),
        }))
        .filter((section) => section.items.length > 0);
}

/** The badge count for an item, or null when there is nothing to show. */
export function navCount(
    counts: NavProps['counts'],
    key: keyof NavProps['counts'] | undefined,
): number | null {
    const value = key ? counts[key] : null;

    return value && value > 0 ? value : null;
}
