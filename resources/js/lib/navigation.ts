import {
    BookOpen,
    ChartColumn,
    ClipboardCheck,
    HandCoins,
    LayoutGrid,
    ScrollText,
    Utensils,
    Settings,
    ShieldCheck,
    ShoppingBasket,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as audit } from '@/routes/audit';
import { index as meals } from '@/routes/meals';
import { index as approvals } from '@/routes/approvals';
import { index as deposits } from '@/routes/deposits';
import { index as grocery } from '@/routes/grocery';
import { index as ledger } from '@/routes/ledger';
import { index as members } from '@/routes/members';
import { index as payables } from '@/routes/payables';
import { index as reports } from '@/routes/reports';
import { edit as messSettings } from '@/routes/mess-settings';
import { index as roles } from '@/routes/roles';
import { index as users } from '@/routes/users';
import type { AppNavSection, NavProps } from '@/types';

/*
 * The sidebar, from docs/layout.md (layer 2). Items declare the permissions
 * that show them; nothing here checks a role. Screens that are not built yet
 * link to a placeholder while they are being built; switch each to
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
                href: grocery(),
                icon: ShoppingBasket,
                permissions: ['grocery.view'],
            },
            {
                title: 'Deposits',
                href: deposits(),
                icon: Wallet,
                permissions: ['deposit.view_own', 'deposit.view_all'],
            },
            {
                title: 'Reports',
                href: reports(),
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
                title: 'Meals',
                href: meals(),
                icon: Utensils,
                permissions: ['meal.view_own', 'meal.view_all', 'meal.record'],
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
                href: payables(),
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
                href: audit(),
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
