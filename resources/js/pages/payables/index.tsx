import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FormDialog } from '@/components/form-dialog';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { Button } from '@/components/ui/button';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { show as showPurchase } from '@/routes/grocery';
import { index, reimburse } from '@/routes/payables';
import type { PayableRow } from '@/types';

type Props = {
    /** Open payables, oldest purchase first. */
    payables: PayableRow[];
    /** Everything still owed, summed by the server. */
    outstanding: string;
};

export default function PayablesIndex({ payables, outstanding }: Props) {
    const __ = useTranslate();
    const { fund } = usePage().props;
    const [paying, setPaying] = useState<PayableRow | null>(null);

    const money = (value: string) => (
        <span className="tabular-nums">{formatMoney(value)}</span>
    );
    const purchase = (payable: PayableRow) => (
        <Link
            href={showPurchase(payable.grocery_purchase_id)}
            className="text-ink hover:text-primary"
        >
            {formatBusinessDate(payable.purchase_date)}
        </Link>
    );
    const reimburseButton = (payable: PayableRow) =>
        payable.can.reimburse ? (
            <Button
                variant="outline"
                size="sm"
                onClick={() => setPaying(payable)}
            >
                {__('Reimburse')}
            </Button>
        ) : null;

    const columns: ResponsiveColumn<PayableRow>[] = [
        { key: 'purchase', header: __('Purchase'), cell: purchase },
        {
            key: 'buyer',
            header: __('Buyer'),
            cell: (payable) => payable.member_name,
        },
        {
            key: 'amount',
            header: __('Owed'),
            align: 'right',
            cell: (payable) => money(payable.amount),
            priority: 'low',
        },
        {
            key: 'reimbursed',
            header: __('Reimbursed'),
            align: 'right',
            cell: (payable) => money(payable.amount_reimbursed),
            priority: 'low',
        },
        {
            key: 'outstanding',
            header: __('Still owed'),
            align: 'right',
            cell: (payable) => money(payable.outstanding),
        },
        { key: 'actions', header: '', cell: reimburseButton },
    ];

    const countLabel =
        payables.length === 1
            ? __('1 open payable')
            : __(':count open payables', { count: payables.length });

    return (
        <>
            <Head title={__('Payables')} />
            <ListTemplate
                header={{
                    title: __('Payables'),
                    description: __(
                        'What the fund owes buyers for purchases it could not cover. Record a reimbursement when you hand the buyer cash.',
                    ),
                }}
                resultCount={countLabel}
                summary={
                    payables.length > 0
                        ? fund.balance !== null
                            ? __(
                                  'Still owed :amount · the fund holds :balance',
                                  {
                                      amount: formatMoney(outstanding),
                                      balance: formatMoney(fund.balance),
                                  },
                              )
                            : __('Still owed :amount', {
                                  amount: formatMoney(outstanding),
                              })
                        : undefined
                }
            >
                {payables.length === 0 ? (
                    <p className="text-ink-muted">
                        {__('Nothing is owed to any buyer.')}
                    </p>
                ) : (
                    <ResponsiveTable
                        caption={__('Open payables')}
                        columns={columns}
                        rows={payables}
                        rowKey={(payable) => payable.id}
                        card={(payable) => ({
                            title: purchase(payable),
                            value: money(payable.outstanding),
                            status: reimburseButton(payable),
                            meta: [payable.member_name],
                        })}
                    />
                )}
            </ListTemplate>

            {paying && (
                <FormDialog
                    open
                    onOpenChange={(open) => !open && setPaying(null)}
                    title={__('Reimburse :name?', { name: paying.member_name })}
                    description={__(
                        ':amount is still owed for the :date purchase. The amount leaves the fund today.',
                        {
                            amount: formatMoney(paying.outstanding),
                            date: formatBusinessDate(paying.purchase_date),
                        },
                    )}
                    action={reimburse(paying.id)}
                    field={{
                        type: 'amount',
                        name: 'amount',
                        label: __('Amount paid (৳)'),
                        defaultValue: paying.outstanding,
                        help: __(
                            'No more than what is owed or what the fund holds.',
                        ),
                    }}
                    submitLabel={__('Record reimbursement')}
                />
            )}
        </>
    );
}

PayablesIndex.layout = {
    breadcrumbs: [{ title: 'Payables', href: index() }],
};
