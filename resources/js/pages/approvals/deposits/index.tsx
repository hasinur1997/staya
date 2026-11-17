import { Head, Link } from '@inertiajs/react';
import { ApprovalTabs } from '@/components/approval-tabs';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/approvals/deposits';
import { show } from '@/routes/deposits';
import type { DepositRow } from '@/types';

type Props = {
    deposits: DepositRow[];
};

export default function DepositQueue({ deposits }: Props) {
    const __ = useTranslate();

    const member = (deposit: DepositRow) => (
        <Link href={show(deposit.id)} className="text-ink hover:text-primary">
            {deposit.member_name}
            {deposit.is_own && (
                <span className="text-small text-ink-muted">
                    {' '}
                    {__('(you)')}
                </span>
            )}
        </Link>
    );
    const amount = (deposit: DepositRow) => (
        <span className="tabular-nums">{formatMoney(deposit.amount)}</span>
    );

    const columns: ResponsiveColumn<DepositRow>[] = [
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
    ];

    return (
        <>
            <Head title={__('Deposits to review')} />
            <ListTemplate
                header={{
                    title: __('Pending approvals'),
                    description: __(
                        'Deposits waiting for a decision, oldest first. Nothing counts until it is approved.',
                    ),
                    children: <ApprovalTabs current="deposits" />,
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
                        })}
                    />
                )}
            </ListTemplate>
        </>
    );
}

DepositQueue.layout = {
    breadcrumbs: [{ title: 'Pending approvals', href: index() }],
};
