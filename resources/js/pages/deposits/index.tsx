import { Combobox } from '@/components/ui/combobox';
import { MonthPicker } from '@/components/ui/month-picker';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import {
    DepositStatusBadge,
    depositNote,
} from '@/components/deposit-status-badge';
import { FormDialog } from '@/components/form-dialog';
import { Pager } from '@/components/pager';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cancel, create, index, show } from '@/routes/deposits';
import type {
    DepositFilters,
    DepositRow,
    DepositStatusValue,
    MemberOption,
    Option,
    Paginated,
} from '@/types';

type Props = {
    deposits: Paginated<DepositRow>;
    filters: DepositFilters;
    statuses: Option<DepositStatusValue>[];
    members: MemberOption[];
    can: { viewAll: boolean; create: boolean; createForOthers: boolean };
};

const ALL = 'all';

export default function DepositsIndex({
    deposits,
    filters,
    statuses,
    members,
    can,
}: Props) {
    const __ = useTranslate();
    const [cancelling, setCancelling] = useState<DepositRow | null>(null);

    const applyFilters = (changes: Partial<DepositFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    status: next.status ?? undefined,
                    member: next.member ?? undefined,
                    month: next.month ?? undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const date = (deposit: DepositRow) => (
        <Link href={show(deposit.id)} className="text-ink hover:text-primary">
            {formatBusinessDate(deposit.deposit_date)}
        </Link>
    );
    const status = (deposit: DepositRow) => (
        <span className="flex flex-col gap-1">
            <DepositStatusBadge
                status={deposit.status}
                label={deposit.status_label}
                selfApproved={deposit.is_self_approved}
            />
            {depositNote(deposit) && (
                <span className="text-small text-ink-muted">
                    {depositNote(deposit)}
                </span>
            )}
        </span>
    );
    const amount = (deposit: DepositRow) => (
        <span className="tabular-nums">{formatMoney(deposit.amount)}</span>
    );
    const cancelButton = (deposit: DepositRow) =>
        deposit.can.cancel ? (
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setCancelling(deposit)}
            >
                {__('Cancel')}
            </Button>
        ) : null;

    const columns: ResponsiveColumn<DepositRow>[] = [
        { key: 'date', header: __('Date'), cell: date },
        ...(can.viewAll
            ? [
                  {
                      key: 'member',
                      header: __('Member'),
                      cell: (deposit: DepositRow) => deposit.member_name,
                  },
              ]
            : []),
        { key: 'status', header: __('Status'), cell: status },
        { key: 'amount', header: __('Amount'), align: 'right', cell: amount },
        { key: 'actions', header: '', cell: cancelButton, priority: 'low' },
    ];

    const newDepositAction =
        can.create || can.createForOthers
            ? {
                  label: can.create
                      ? __('Submit deposit')
                      : __('Record deposit'),
                  href: create(),
                  icon: Plus,
              }
            : undefined;

    return (
        <>
            <Head title={__('Deposits')} />
            <ListTemplate
                header={{
                    title: can.viewAll ? __('Deposits') : __('My deposits'),
                    description: __(
                        'Only approved deposits count towards the fund and your monthly share.',
                    ),
                    primaryAction: newDepositAction,
                }}
                filters={
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="deposit-month">{__('Month')}</Label>
                        <MonthPicker
                            id="deposit-month"
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
                        <div className="flex flex-col gap-1 md:flex-row md:items-center md:gap-2">
                            <Label htmlFor="deposit-status">
                                {__('Status')}
                            </Label>
                            <Combobox
                                id="deposit-status"
                                value={filters.status ?? ALL}
                                onValueChange={(next) =>
                                    applyFilters({
                                        status:
                                            next === ALL
                                                ? null
                                                : (next as DepositStatusValue),
                                    })
                                }
                                options={[
                                    { value: ALL, label: __('All statuses') },
                                    ...statuses,
                                ]}
                                searchPlaceholder={__('Search')}
                                emptyText={__('Nothing found.')}
                                className="md:w-44"
                            />
                        </div>
                        {can.viewAll && (
                            <div className="flex flex-col gap-1 md:flex-row md:items-center md:gap-2">
                                <Label htmlFor="deposit-member">
                                    {__('Member')}
                                </Label>
                                <Combobox
                                    id="deposit-member"
                                    value={
                                        filters.member
                                            ? String(filters.member)
                                            : ALL
                                    }
                                    onValueChange={(next) =>
                                        applyFilters({
                                            member:
                                                next === ALL
                                                    ? null
                                                    : Number(next),
                                        })
                                    }
                                    options={[
                                        {
                                            value: ALL,
                                            label: __('All members'),
                                        },
                                        ...members.map((member) => ({
                                            value: String(member.id),
                                            label: member.name,
                                        })),
                                    ]}
                                    searchPlaceholder={__('Search')}
                                    emptyText={__('Nothing found.')}
                                    className="md:w-56"
                                />
                            </div>
                        )}
                    </>
                }
                activeFilterCount={
                    (filters.status ? 1 : 0) + (filters.member ? 1 : 0)
                }
                resultCount={
                    deposits.total === 1
                        ? __('1 deposit')
                        : __(':count deposits', { count: deposits.total })
                }
                pagination={<Pager page={deposits} />}
            >
                {deposits.data.length === 0 ? (
                    <p className="text-ink-muted">{__('No deposits yet.')}</p>
                ) : (
                    <ResponsiveTable
                        caption={__('Deposits')}
                        columns={columns}
                        rows={deposits.data}
                        rowKey={(deposit) => deposit.id}
                        card={(deposit) => ({
                            title: date(deposit),
                            value: amount(deposit),
                            status: status(deposit),
                            meta: can.viewAll ? [deposit.member_name] : [],
                        })}
                    />
                )}
            </ListTemplate>

            {cancelling && (
                <FormDialog
                    open
                    onOpenChange={(open) => !open && setCancelling(null)}
                    title={__('Cancel this deposit?')}
                    description={__(
                        ':amount dated :date. It was never counted, so nothing else changes.',
                        {
                            amount: formatMoney(cancelling.amount),
                            date: formatBusinessDate(cancelling.deposit_date),
                        },
                    )}
                    action={cancel(cancelling.id)}
                    submitLabel={__('Cancel deposit')}
                    destructive
                />
            )}
        </>
    );
}

DepositsIndex.layout = {
    breadcrumbs: [{ title: 'Deposits', href: index() }],
};
