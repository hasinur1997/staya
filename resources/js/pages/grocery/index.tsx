import { Combobox } from '@/components/ui/combobox';
import { MemberName } from '@/components/member-name';
import { DatePicker } from '@/components/ui/date-picker';
import { MonthPicker } from '@/components/ui/month-picker';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Pager } from '@/components/pager';
import { PurchaseStatusBadge } from '@/components/purchase-status-badge';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { Label } from '@/components/ui/label';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { create, index, show } from '@/routes/grocery';
import type {
    MemberOption,
    Paginated,
    PurchaseFilters,
    PurchaseRow,
} from '@/types';

type Props = {
    purchases: Paginated<PurchaseRow>;
    filters: PurchaseFilters;
    /** Grand total of every purchase matching the filters. */
    total: string;
    buyers: MemberOption[];
    /** The server's time when the page was built (ISO 8601). */
    serverNow: string;
    can: { create: boolean };
};

const ALL = 'all';

export default function GroceryIndex({
    purchases,
    filters,
    total,
    buyers,
    serverNow,
    can,
}: Props) {
    const __ = useTranslate();

    const applyFilters = (changes: Partial<PurchaseFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    month: next.month ?? undefined,
                    from: next.month ? undefined : (next.from ?? undefined),
                    to: next.month ? undefined : (next.to ?? undefined),
                    buyer: next.buyer ?? undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const date = (purchase: PurchaseRow) => (
        <Link href={show(purchase.id)} className="text-ink hover:text-primary">
            {formatBusinessDate(purchase.purchase_date)}
        </Link>
    );
    const itemSummary = (purchase: PurchaseRow) => {
        const more = purchase.item_count - purchase.item_names.length;

        return more > 0
            ? __(':items and :count more', {
                  items: purchase.item_names.join(', '),
                  count: more,
              })
            : purchase.item_names.join(', ');
    };
    const status = (purchase: PurchaseRow) => (
        <PurchaseStatusBadge purchase={purchase} serverNow={serverNow} />
    );
    const amount = (purchase: PurchaseRow) => (
        <span className="tabular-nums">
            {formatMoney(purchase.grand_total)}
        </span>
    );

    const columns: ResponsiveColumn<PurchaseRow>[] = [
        { key: 'date', header: __('Date'), cell: date },
        {
            key: 'buyer',
            header: __('Buyer'),
            cell: (purchase) => (
                <MemberName
                    id={purchase.buyer_member_id}
                    name={purchase.buyer_name}
                />
            ),
        },
        {
            key: 'paid_by',
            header: __('Paid by'),
            cell: (purchase) => (
                <MemberName
                    id={purchase.paid_by_member_id}
                    name={purchase.paid_by_name}
                />
            ),
            priority: 'low',
        },
        {
            key: 'items',
            header: __('Items'),
            cell: itemSummary,
            priority: 'low',
        },
        { key: 'status', header: __('Your edit'), cell: status },
        { key: 'total', header: __('Total'), align: 'right', cell: amount },
    ];

    const countLabel =
        purchases.total === 1
            ? __('1 purchase')
            : __(':count purchases', { count: purchases.total });

    return (
        <>
            <Head title={__('Grocery purchases')} />
            <ListTemplate
                header={{
                    title: __('Grocery purchases'),
                    description: __(
                        'Everything bought for the mess, paid by the fund or by a member the fund then repays.',
                    ),
                    primaryAction: can.create
                        ? {
                              label: __('Add purchase'),
                              href: create(),
                              icon: Plus,
                          }
                        : undefined,
                }}
                filters={
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="purchase-month">{__('Month')}</Label>
                        <MonthPicker
                            id="purchase-month"
                            value={filters.month ?? ''}
                            onValueChange={(month) =>
                                applyFilters({ month: month || null })
                            }
                            placeholder={__('Any month')}
                            className="w-48"
                        />
                    </div>
                }
                moreFilters={
                    <>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="purchase-from">{__('From')}</Label>
                            <DatePicker
                                id="purchase-from"
                                value={filters.from ?? ''}
                                max={filters.to}
                                onValueChange={(from) =>
                                    applyFilters({
                                        month: null,
                                        from: from || null,
                                    })
                                }
                                placeholder={__('Any date')}
                                clearable
                                className="md:w-44"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="purchase-to">{__('To')}</Label>
                            <DatePicker
                                id="purchase-to"
                                value={filters.to ?? ''}
                                min={filters.from}
                                onValueChange={(to) =>
                                    applyFilters({
                                        month: null,
                                        to: to || null,
                                    })
                                }
                                placeholder={__('Any date')}
                                clearable
                                className="md:w-44"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="purchase-buyer">
                                {__('Buyer')}
                            </Label>
                            <Combobox
                                id="purchase-buyer"
                                value={
                                    filters.buyer ? String(filters.buyer) : ALL
                                }
                                onValueChange={(next) =>
                                    applyFilters({
                                        buyer:
                                            next === ALL ? null : Number(next),
                                    })
                                }
                                options={[
                                    { value: ALL, label: __('All buyers') },
                                    ...buyers.map((buyer) => ({
                                        value: String(buyer.id),
                                        label: buyer.name,
                                    })),
                                ]}
                                searchPlaceholder={__('Search')}
                                emptyText={__('Nothing found.')}
                                className="md:w-56"
                            />
                        </div>
                    </>
                }
                activeFilterCount={
                    (filters.from ? 1 : 0) +
                    (filters.to ? 1 : 0) +
                    (filters.buyer ? 1 : 0)
                }
                resultCount={countLabel}
                summary={
                    purchases.total > 0
                        ? __('Total :amount · :count', {
                              amount: formatMoney(total),
                              count: countLabel,
                          })
                        : undefined
                }
                pagination={<Pager page={purchases} />}
            >
                {purchases.data.length === 0 ? (
                    <p className="text-ink-muted">
                        {__('No purchases to show.')}
                    </p>
                ) : (
                    <ResponsiveTable
                        caption={__('Grocery purchases')}
                        columns={columns}
                        rows={purchases.data}
                        rowKey={(purchase) => purchase.id}
                        card={(purchase) => ({
                            title: date(purchase),
                            value: amount(purchase),
                            status: status(purchase),
                            meta: [
                                <MemberName
                                    id={purchase.buyer_member_id}
                                    name={purchase.buyer_name}
                                />,
                                <span>
                                    {__('Paid by')}{' '}
                                    <MemberName
                                        id={purchase.paid_by_member_id}
                                        name={purchase.paid_by_name}
                                    />
                                </span>,
                                itemSummary(purchase),
                            ],
                        })}
                    />
                )}
            </ListTemplate>
        </>
    );
}

GroceryIndex.layout = {
    breadcrumbs: [{ title: 'Grocery purchases', href: index() }],
};
