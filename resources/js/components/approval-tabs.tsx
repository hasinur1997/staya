import { Link } from '@inertiajs/react';
import { useCan } from '@/hooks/use-can';
import { useTranslate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as depositQueue } from '@/routes/approvals/deposits';
import { index as registrationQueue } from '@/routes/approvals/registrations';

/**
 * The queues under Pending approvals. Each tab shows only for the
 * permission that reviews it; the server still guards every queue.
 */
export function ApprovalTabs({
    current,
}: {
    current: 'deposits' | 'registrations';
}) {
    const __ = useTranslate();
    const can = useCan();

    const tabs = [
        {
            key: 'deposits',
            label: __('Deposits'),
            href: depositQueue(),
            visible: can('deposit.approve'),
        },
        {
            key: 'registrations',
            label: __('Registrations'),
            href: registrationQueue(),
            visible: can('member.approve_registration'),
        },
    ].filter((tab) => tab.visible);

    if (tabs.length < 2) {
        return null;
    }

    return (
        <nav
            aria-label={__('Approval queues')}
            className="flex gap-1 border-b border-border"
        >
            {tabs.map((tab) => (
                <Link
                    key={tab.key}
                    href={tab.href}
                    aria-current={tab.key === current ? 'page' : undefined}
                    className={cn(
                        '-mb-px border-b-2 px-3 py-2 text-ink-muted hover:bg-surface-raised',
                        tab.key === current
                            ? 'border-primary text-primary'
                            : 'border-transparent',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
