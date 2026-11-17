import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { FormDialog } from '@/components/form-dialog';
import { PurchaseStatusBadge } from '@/components/purchase-status-badge';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { DetailTemplate } from '@/components/templates/detail-template';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatBusinessDate, formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import {
    edit,
    index,
    receipt,
    show,
    voidMethod as voidPurchase,
} from '@/routes/grocery';
import { show as showMember } from '@/routes/members';
import { index as payables } from '@/routes/payables';
import type { PageAction, PurchaseDetail, PurchaseItem } from '@/types';

type Props = {
    purchase: PurchaseDetail;
    /** The server's time when the page was built (ISO 8601). */
    serverNow: string;
    can: { viewAny: boolean; viewBuyer: boolean; viewPayables: boolean };
};

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-1 md:grid md:grid-cols-3 md:gap-4">
            <dt className="text-small text-ink-muted">{label}</dt>
            <dd className="text-ink md:col-span-2">{children}</dd>
        </div>
    );
}

function Amount({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-4">
            <dt className="text-small text-ink-muted">{label}</dt>
            <dd className="text-ink tabular-nums">{formatMoney(value)}</dd>
        </div>
    );
}

export default function GroceryShow({ purchase, serverNow, can }: Props) {
    const __ = useTranslate();
    const [voiding, setVoiding] = useState(false);
    const title = __(':date purchase', {
        date: formatBusinessDate(purchase.purchase_date),
    });

    setLayoutProps({
        breadcrumbs: [
            ...(can.viewAny
                ? [{ title: __('Grocery purchases'), href: index() }]
                : []),
            { title, href: show(purchase.id) },
        ],
    });

    const menuActions: PageAction[] = purchase.can.delete
        ? [
              {
                  label: __('Delete'),
                  icon: Trash2,
                  destructive: true,
                  onClick: () => setVoiding(true),
              },
          ]
        : [];

    const quantity = (item: PurchaseItem) => (
        <span className="tabular-nums">
            {item.quantity.replace(/\.?0+$/, '')} {item.unit_label}
        </span>
    );
    const lineTotal = (item: PurchaseItem) => (
        <span className="tabular-nums">{formatMoney(item.line_total)}</span>
    );
    const columns: ResponsiveColumn<PurchaseItem>[] = [
        { key: 'item', header: __('Item'), cell: (item) => item.item_name },
        {
            key: 'quantity',
            header: __('Quantity'),
            align: 'right',
            cell: quantity,
        },
        {
            key: 'unit_price',
            header: __('Unit price'),
            align: 'right',
            cell: (item) => (
                <span className="tabular-nums">
                    {formatMoney(item.unit_price)}
                </span>
            ),
        },
        {
            key: 'line_total',
            header: __('Line total'),
            align: 'right',
            cell: lineTotal,
        },
    ];

    const by = (time: string, name: string | null) =>
        name
            ? __(':time by :name', { time: formatDateTime(time), name })
            : formatDateTime(time);

    return (
        <>
            <Head title={title} />
            <DetailTemplate
                header={{
                    overline: __('Grocery purchase'),
                    title: formatMoney(purchase.grand_total),
                    status: (
                        <PurchaseStatusBadge
                            purchase={purchase}
                            serverNow={serverNow}
                        />
                    ),
                    description: __(':buyer · bought :date', {
                        buyer: purchase.buyer_name,
                        date: formatBusinessDate(purchase.purchase_date),
                    }),
                    primaryAction: purchase.can.update
                        ? {
                              label: __('Edit'),
                              href: edit(purchase.id),
                              icon: Pencil,
                          }
                        : undefined,
                    menuActions,
                }}
                aside={
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-subhead">
                                {__('Payment')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <dl className="flex flex-col gap-3">
                                <Amount
                                    label={__('Total')}
                                    value={purchase.grand_total}
                                />
                                <Amount
                                    label={__('Paid from the fund')}
                                    value={purchase.fund_paid}
                                />
                                {purchase.payable && (
                                    <>
                                        <Amount
                                            label={__('Owed to the buyer')}
                                            value={purchase.payable.amount}
                                        />
                                        <Amount
                                            label={__('Reimbursed so far')}
                                            value={
                                                purchase.payable
                                                    .amount_reimbursed
                                            }
                                        />
                                        <Amount
                                            label={__('Still owed')}
                                            value={purchase.payable.outstanding}
                                        />
                                    </>
                                )}
                            </dl>
                            {purchase.status === 'voided' && (
                                <p className="text-small text-ink-muted">
                                    {__(
                                        'This purchase was deleted, so its payment was reversed and it no longer counts.',
                                    )}
                                </p>
                            )}
                            {purchase.payable && can.viewPayables && (
                                <Link
                                    href={payables()}
                                    className="text-small text-primary hover:underline"
                                >
                                    {__('Open payables')}
                                </Link>
                            )}
                        </CardContent>
                    </Card>
                }
            >
                <Card>
                    <CardHeader>
                        <CardTitle className="text-subhead">
                            {__('Items')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveTable
                            caption={__('Items')}
                            columns={columns}
                            rows={purchase.items}
                            rowKey={(item) => item.id}
                            card={(item) => ({
                                title: item.item_name,
                                value: lineTotal(item),
                                meta: [
                                    quantity(item),
                                    __(':price each', {
                                        price: formatMoney(item.unit_price),
                                    }),
                                ],
                            })}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-subhead">
                            {__('Details')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="flex flex-col gap-4">
                            <Row label={__('Buyer')}>
                                {can.viewBuyer ? (
                                    <Link
                                        href={showMember(
                                            purchase.buyer_member_id,
                                        )}
                                        className="text-primary hover:underline"
                                    >
                                        {purchase.buyer_name}
                                    </Link>
                                ) : (
                                    purchase.buyer_name
                                )}
                            </Row>
                            <Row label={__('Date bought')}>
                                {formatBusinessDate(purchase.purchase_date)}
                            </Row>
                            {purchase.notes && (
                                <Row label={__('Notes')}>{purchase.notes}</Row>
                            )}
                            <Row label={__('Receipt')}>
                                {purchase.has_receipt ? (
                                    <a
                                        href={receipt.url(purchase.id)}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-primary hover:underline"
                                    >
                                        {__('View receipt')}
                                    </a>
                                ) : (
                                    __('None')
                                )}
                            </Row>
                            <Row label={__('Recorded')}>
                                {by(purchase.created_at, purchase.created_by)}
                            </Row>
                            {purchase.updated_at && (
                                <Row label={__('Last edited')}>
                                    {by(
                                        purchase.updated_at,
                                        purchase.updated_by,
                                    )}
                                </Row>
                            )}
                            {purchase.voided_at && (
                                <Row label={__('Deleted')}>
                                    {by(purchase.voided_at, purchase.voided_by)}
                                </Row>
                            )}
                            {purchase.void_reason && (
                                <Row label={__('Reason')}>
                                    {purchase.void_reason}
                                </Row>
                            )}
                        </dl>
                    </CardContent>
                </Card>
            </DetailTemplate>

            <FormDialog
                open={voiding}
                onOpenChange={setVoiding}
                title={__('Delete this purchase?')}
                description={__(
                    'It is hidden from the lists and its payment is reversed, so the money goes back to the fund. The record is kept in the audit log.',
                )}
                action={voidPurchase(purchase.id)}
                field={
                    purchase.void_needs_reason
                        ? {
                              type: 'reason',
                              name: 'reason',
                              label: __('Reason'),
                          }
                        : undefined
                }
                submitLabel={__('Delete purchase')}
                destructive
            />
        </>
    );
}
