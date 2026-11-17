import { Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { useCan } from '@/hooks/use-can';
import { useTranslate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as depositQueue } from '@/routes/approvals/deposits';
import { index as registrationQueue } from '@/routes/approvals/registrations';
import { index as deposits } from '@/routes/deposits';
import { index as members } from '@/routes/members';

type Tab = {
    key: string;
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    count?: number | null;
};

function SectionTabs({
    label,
    tabs,
    current,
}: {
    label: string;
    tabs: Tab[];
    current: string;
}) {
    return (
        <nav aria-label={label} className="flex gap-1 border-b border-border">
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    aria-current={tab.key === current ? 'page' : undefined}
                    className={cn(
                        '-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-ink-muted hover:bg-surface-raised',
                        tab.key === current
                            ? 'border-primary text-primary'
                            : 'border-transparent',
                    )}
                >
                    {tab.label}
                    {tab.count ? (
                        <Badge variant="count">{tab.count}</Badge>
                    ) : null}
                </Link>
            ))}
        </nav>
    );
}

/**
 * All deposits, and the ones waiting for a decision. Only people who approve
 * deposits get the second tab; the server still guards the queue.
 */
export function DepositTabs({ current }: { current: 'all' | 'pending' }) {
    const __ = useTranslate();
    const can = useCan();
    const { counts } = usePage().props.nav;

    if (!can('deposit.approve')) {
        return null;
    }

    return (
        <SectionTabs
            label={__('Deposit lists')}
            current={current}
            tabs={[
                { key: 'all', label: __('All deposits'), href: deposits() },
                {
                    key: 'pending',
                    label: __('Pending approval'),
                    href: depositQueue(),
                    count: counts.pendingDeposits,
                },
            ]}
        />
    );
}

/** Members, and the sign-ups waiting for a decision. */
export function MemberTabs({
    current,
}: {
    current: 'members' | 'registrations';
}) {
    const __ = useTranslate();
    const can = useCan();
    const { counts } = usePage().props.nav;

    if (!can('member.approve_registration')) {
        return null;
    }

    return (
        <SectionTabs
            label={__('Member lists')}
            current={current}
            tabs={[
                { key: 'members', label: __('All members'), href: members() },
                {
                    key: 'registrations',
                    label: __('Registrations'),
                    href: registrationQueue(),
                    count: counts.pendingRegistrations,
                },
            ]}
        />
    );
}
