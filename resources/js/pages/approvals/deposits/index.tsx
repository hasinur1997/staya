import { Head, Link } from '@inertiajs/react';
import { MemberName } from '@/components/member-name';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { DepositTabs } from '@/components/review-tabs';
import { FormDialog } from '@/components/form-dialog';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { Button } from '@/components/ui/button';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/approvals/deposits';
import { approve, index as allDeposits, show } from '@/routes/deposits';
import type { DepositRow } from '@/types';

type QueueRow = DepositRow & {
    /** The viewer may approve it; false for their own deposit when self-approval is not allowed. */
    can_approve: boolean;
};

type Props = {
    deposits: QueueRow[];
};

export default function DepositQueue({ deposits }: Props) {
    const __ = useTranslate();
    const [reviewing, setReviewing] = useState<QueueRow | null>(null);

    const member = (deposit: QueueRow) => (
        <>
            <MemberName
                id={deposit.member_id}
                name={deposit.member_name}
                linked
            />
            {deposit.is_own && (
                <span className="text-caption text-ink-muted">
                    {' '}
                    {__('(you)')}
                </span>
            )}
        </>
    );
    const amount = (deposit: QueueRow) => (
        <span className="tabular-nums">{formatMoney(deposit.amount)}</span>
    );

    const decision = (deposit: QueueRow) =>
        deposit.can_approve ? (
            <Button
                type="button"
                size="sm"
                variant="success"
                onClick={() => setReviewing(deposit)}
            >
                <Check />
                {__('Approve')}
            </Button>
        ) : (
            <Button size="sm" variant="outline" asChild>
                <Link href={show(deposit.id)}>{__('Review')}</Link>
            </Button>
        );

    const columns: ResponsiveColumn<QueueRow>[] = [
        { key: 'member', header: __('Member'), cell: member },
        {
            key: 'date',
            header: __('Date paid'),
            cell: (deposit) => formatBusinessDate(deposit.deposit_date),
        },
        {
            key: 'method',
            header: __('Method'),
            cell: (deposit) => deposit.method_label,
            priority: 'low',
        },
        { key: 'amount', header: __('Amount'), align: 'right', cell: amount },
        {
            key: 'action',
            header: __('Action'),
            align: 'right',
            cell: (deposit) => decision(deposit),
        },
    ];

    return (
        <>
            <Head title={__('Deposits to review')} />
            <ListTemplate
                header={{
                    title: __('Deposits'),
                    description: __(
                        'Deposits waiting for a decision, oldest first. Nothing counts until it is approved.',
                    ),
                    children: <DepositTabs current="pending" />,
                }}
                resultCount={
                    deposits.length === 1
                        ? __('1 deposit')
                        : __(':count deposits', { count: deposits.length })
                }
            >
                {deposits.length === 0 ? (
                    <p className="text-ink-muted">
                        {__('No deposits are waiting.')}
                    </p>
                ) : (
                    <ResponsiveTable
                        caption={__('Pending deposits')}
                        columns={columns}
                        rows={deposits}
                        rowKey={(deposit) => deposit.id}
                        card={(deposit) => ({
                            title: member(deposit),
                            value: amount(deposit),
                            meta: [formatBusinessDate(deposit.deposit_date)],
                            actions: decision(deposit),
                        })}
                    />
                )}
            </ListTemplate>

            <FormDialog
                open={reviewing !== null}
                onOpenChange={(open) => !open && setReviewing(null)}
                title={__('Approve :amount?', {
                    amount: reviewing ? formatMoney(reviewing.amount) : '',
                })}
                description={
                    reviewing
                        ? __(
                              ':member paid on :date. The fund balance goes up by this amount.',
                              {
                                  member: reviewing.member_name,
                                  date: formatBusinessDate(
                                      reviewing.deposit_date,
                                  ),
                              },
                          )
                        : ''
                }
                action={reviewing ? approve(reviewing.id) : approve(0)}
                submitLabel={__('Approve')}
            />
        </>
    );
}

DepositQueue.layout = {
    breadcrumbs: [
        { title: 'Deposits', href: allDeposits() },
        { title: 'Pending approval', href: index() },
    ],
};
