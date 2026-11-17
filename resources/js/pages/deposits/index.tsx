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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
                        <Input
                            id="deposit-month"
                            type="month"
                            value={filters.month ?? ''}
                            onChange={(event) =>
                                applyFilters({
                                    month: event.target.value || null,
                                })
                            }
                            className="w-44"
                        />
                    </div>
                }
                moreFilters={
                    <>
                        <div className="flex flex-col gap-1 md:flex-row md:items-center md:gap-2">
                            <Label htmlFor="deposit-status">
                                {__('Status')}
                            </Label>
                            <Select
                                value={filters.status ?? ALL}
                                onValueChange={(next) =>
                                    applyFilters({
                                        status:
                                            next === ALL
                                                ? null
                                                : (next as DepositStatusValue),
                                    })
                                }
                            >
                                <SelectTrigger
                                    id="deposit-status"
                                    className="md:w-44"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>
                                        {__('All statuses')}
                                    </SelectItem>
                                    {statuses.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {can.viewAll && (
                            <div className="flex flex-col gap-1 md:flex-row md:items-center md:gap-2">
                                <Label htmlFor="deposit-member">
                                    {__('Member')}
                                </Label>
                                <Select
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
                                >
                                    <SelectTrigger
                                        id="deposit-member"
                                        className="md:w-56"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL}>
                                            {__('All members')}
                                        </SelectItem>
                                        {members.map((member) => (
                                            <SelectItem
                                                key={member.id}
                                                value={String(member.id)}
                                            >
                                                {member.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
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
