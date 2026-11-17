import { Head, Link, router } from '@inertiajs/react';
import { Lock, LockOpen, Printer } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/form-dialog';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { SignedMoney } from '@/components/signed-money';
import { StatCard } from '@/components/stat-card';
import { ReportTemplate } from '@/components/templates/report-template';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { MonthPicker } from '@/components/ui/month-picker';
import { formatBusinessDate, formatDateTime, formatMonth } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { show as showDeposit } from '@/routes/deposits';
import { show as showMember } from '@/routes/members';
import { index as payables } from '@/routes/payables';
import { close, index, reopen } from '@/routes/reports';
import type {
    MemberOption,
    MonthlyMemberRow,
    Report,
    ReportFilters,
} from '@/types';

type Props = {
    report: Report;
    filters: ReportFilters;
    members: MemberOption[];
    currentMonth: string;
    can: { viewAll: boolean; close: boolean; reopen: boolean };
};

const ALL = 'all';

export default function ReportsIndex({
    report,
    filters,
    members,
    currentMonth,
    can,
}: Props) {
    const __ = useTranslate();
    const [dialog, setDialog] = useState<'close' | 'reopen' | null>(null);

    const apply = (changes: Partial<ReportFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    month: next.from ? undefined : (next.month ?? undefined),
                    from: next.from ?? undefined,
                    to: next.to ?? undefined,
                    member: next.member ?? undefined,
                },
            }),
            {},
            { preserveScroll: true },
        );
    };

    const period = report.month
        ? formatMonth(report.month)
        : __(':from to :to', {
              from: formatBusinessDate(report.from),
              to: formatBusinessDate(report.to),
          });
    const closed = report.closure?.status === 'closed';
    const pending = report.unapproved?.pending.count ?? 0;

    const money = (value: string) => (
        <span className="tabular-nums">{formatMoney(value)}</span>
    );
    const signed = (value: string) => (
        <SignedMoney
            value={value}
            positiveLabel={__('credit')}
            negativeLabel={__('due')}
        />
    );

    const summary = [
        ...(report.ledger
            ? [
                  <StatCard
                      key="opening"
                      label={__('Opening balance')}
                      value={formatMoney(report.ledger.opening)}
                  />,
              ]
            : []),
        <StatCard
            key="deposits"
            label={__('Deposits')}
            value={formatMoney(report.figures.deposits)}
            hint={__('Approved, by deposit date')}
        />,
        <StatCard
            key="expenses"
            label={__('Expenses')}
            value={formatMoney(report.figures.expenses)}
            hint={__('Full purchase totals')}
        />,
        ...(report.ledger
            ? [
                  <StatCard
                      key="closing"
                      label={__('Closing balance')}
                      value={formatMoney(report.ledger.closing)}
                  />,
              ]
            : []),
        <StatCard
            key="members"
            label={__('Active members')}
            value={String(report.figures.active_members)}
        />,
        <StatCard
            key="share"
            label={
                report.figures.split === 'meals'
                    ? __('Cost per meal')
                    : __('Share per member')
            }
            value={formatMoney(report.figures.share)}
            hint={
                report.figures.leftover !== '0.00'
                    ? __(':amount left over', {
                          amount: formatMoney(report.figures.leftover),
                      })
                    : report.figures.split === 'meals'
                      ? __(':count meals', {
                            count: report.figures.total_meals,
                        })
                      : undefined
            }
        />,
        ...(report.ledger
            ? [
                  <StatCard
                      key="adjustments"
                      label={__('Adjustments')}
                      value={<SignedMoney value={report.ledger.adjustments} />}
                      hint={__('Not shared between members')}
                  />,
                  <StatCard
                      key="reimbursements"
                      label={__('Reimbursements')}
                      value={formatMoney(report.ledger.reimbursements)}
                      hint={__('Not shared between members')}
                  />,
              ]
            : []),
    ];

    const columns: ResponsiveColumn<MonthlyMemberRow>[] = [
        {
            key: 'name',
            header: __('Member'),
            cell: (row) =>
                can.viewAll ? (
                    <Link
                        href={showMember(row.member_id)}
                        className="text-ink hover:text-primary"
                    >
                        {row.name}
                    </Link>
                ) : (
                    row.name
                ),
        },
        {
            key: 'deposits',
            header: __('Deposits'),
            align: 'right',
            cell: (row) => money(row.deposits),
        },
        ...(report.figures.split === 'meals'
            ? [
                  {
                      key: 'meals',
                      header: __('Meals'),
                      align: 'right' as const,
                      cell: (row: MonthlyMemberRow) => String(row.meals),
                  },
              ]
            : []),
        {
            key: 'share',
            header: __('Share'),
            align: 'right',
            priority: 'low',
            cell: (row) => money(row.share),
        },
        {
            key: 'due_credit',
            header: __('Due or credit'),
            align: 'right',
            cell: (row) => signed(row.due_credit),
        },
    ];

    return (
        <>
            <Head title={__('Monthly report')} />
            <ReportTemplate
                header={{
                    overline: __('Report'),
                    title: period,
                    status: report.closure ? (
                        <Badge variant={closed ? 'success' : 'neutral'}>
                            {closed ? __('Closed') : __('Open')}
                        </Badge>
                    ) : undefined,
                    description: __(
                        'Evaluated in Asia/Dhaka time. Each month stands alone; nothing carries forward.',
                    ),
                    primaryAction: can.close
                        ? {
                              label: __('Close month'),
                              icon: Lock,
                              onClick: () => setDialog('close'),
                          }
                        : undefined,
                    secondaryActions: [
                        ...(can.reopen
                            ? [
                                  {
                                      label: __('Reopen month'),
                                      icon: LockOpen,
                                      onClick: () => setDialog('reopen'),
                                  },
                              ]
                            : []),
                        {
                            label: __('Print'),
                            icon: Printer,
                            onClick: () => window.print(),
                        },
                    ],
                }}
                period={
                    <>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="report-month">{__('Month')}</Label>
                            <MonthPicker
                                id="report-month"
                                value={report.month ?? ''}
                                onValueChange={(month) =>
                                    apply({
                                        month: month || currentMonth,
                                        from: null,
                                        to: null,
                                    })
                                }
                                clearable={false}
                                className="w-48"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="report-from">{__('From')}</Label>
                            <DatePicker
                                id="report-from"
                                value={filters.from ?? ''}
                                max={filters.to ?? undefined}
                                placeholder={__('Custom range')}
                                clearable
                                onValueChange={(from) =>
                                    from
                                        ? apply({
                                              from,
                                              to: filters.to ?? from,
                                          })
                                        : apply({
                                              from: null,
                                              to: null,
                                              month: currentMonth,
                                          })
                                }
                                className="md:w-48"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="report-to">{__('To')}</Label>
                            <DatePicker
                                id="report-to"
                                value={filters.to ?? ''}
                                min={filters.from ?? undefined}
                                placeholder={__('Custom range')}
                                disabled={!filters.from}
                                onValueChange={(to) => to && apply({ to })}
                                className="md:w-48"
                            />
                        </div>
                        {can.viewAll && (
                            <div className="flex flex-col gap-1">
                                <Label htmlFor="report-member">
                                    {__('Member')}
                                </Label>
                                <Combobox
                                    id="report-member"
                                    value={
                                        filters.member
                                            ? String(filters.member)
                                            : ALL
                                    }
                                    onValueChange={(next) =>
                                        apply({
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
                summary={summary}
            >
                <Card>
                    <CardHeader>
                        <CardTitle className="text-subhead">
                            {can.viewAll
                                ? __('Each member')
                                : __('Your figures')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {report.members.length === 0 ? (
                            <p className="text-small text-ink-muted">
                                {__('No members to show for this period.')}
                            </p>
                        ) : (
                            <ResponsiveTable
                                caption={__(
                                    'Deposits, share and due or credit per member',
                                )}
                                columns={columns}
                                rows={report.members}
                                rowKey={(row) => row.member_id}
                                card={(row) => ({
                                    title: row.name,
                                    value: signed(row.due_credit),
                                    meta: [
                                        __('Deposited :amount', {
                                            amount: formatMoney(row.deposits),
                                        }),
                                    ],
                                })}
                            />
                        )}
                    </CardContent>
                </Card>

                {report.unapproved && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-subhead">
                                {__('Deposits not counted')}
                            </CardTitle>
                            <p className="text-small text-ink-muted">
                                {__(
                                    'Pending and rejected deposits dated in this period never affect the balance or anyone’s due or credit.',
                                )}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <dl className="flex flex-col gap-3">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">
                                        {__('Pending (:count)', {
                                            count: report.unapproved.pending
                                                .count,
                                        })}
                                    </dt>
                                    <dd>
                                        {money(
                                            report.unapproved.pending.amount,
                                        )}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-ink-muted">
                                        {__('Rejected (:count)', {
                                            count: report.unapproved.rejected
                                                .count,
                                        })}
                                    </dt>
                                    <dd>
                                        {money(
                                            report.unapproved.rejected.amount,
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                )}

                {report.self_approved && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-subhead">
                                {__('Self-approved deposits')}
                            </CardTitle>
                            <p className="text-small text-ink-muted">
                                {__(
                                    'Approved by the person who submitted them. Worth a second look.',
                                )}
                            </p>
                        </CardHeader>
                        <CardContent>
                            {report.self_approved.length === 0 ? (
                                <p className="text-small text-ink-muted">
                                    {__('None in this period.')}
                                </p>
                            ) : (
                                <ul className="flex flex-col divide-y divide-border">
                                    {report.self_approved.map((deposit) => (
                                        <li
                                            key={deposit.id}
                                            className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                        >
                                            <span className="flex flex-col gap-1">
                                                <Link
                                                    href={showDeposit(
                                                        deposit.id,
                                                    )}
                                                    className="text-ink hover:text-primary"
                                                >
                                                    {deposit.member_name}
                                                </Link>
                                                <span className="text-small text-ink-muted">
                                                    {formatBusinessDate(
                                                        deposit.deposit_date,
                                                    )}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-2">
                                                <Badge variant="warning">
                                                    {__('Self-approved')}
                                                </Badge>
                                                {money(deposit.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}

                {report.open_payables && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-subhead">
                                {__('Open payables')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex items-center justify-between gap-4">
                            <span className="text-ink-muted">
                                {__(':count open, owed to members', {
                                    count: report.open_payables.count,
                                })}
                            </span>
                            <span className="flex items-center gap-4">
                                {money(report.open_payables.amount)}
                                <Link
                                    href={payables()}
                                    className="text-small text-primary hover:underline print:hidden"
                                >
                                    {__('View payables')}
                                </Link>
                            </span>
                        </CardContent>
                    </Card>
                )}

                {report.closure && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-subhead">
                                {__('Month status')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1 text-small">
                            {closed && report.closure.closed_at ? (
                                <p>
                                    {__(
                                        'Closed :time by :name. Nothing dated in this month can change.',
                                        {
                                            time: formatDateTime(
                                                report.closure.closed_at,
                                            ),
                                            name:
                                                report.closure.closed_by ?? '',
                                        },
                                    )}
                                </p>
                            ) : (
                                <p>
                                    {__(
                                        'Open. Deposits, purchases and adjustments dated in this month can still change.',
                                    )}
                                </p>
                            )}
                            {!closed && report.closure.reopened_at && (
                                <p className="text-ink-muted">
                                    {__('Reopened :time by :name: :reason', {
                                        time: formatDateTime(
                                            report.closure.reopened_at,
                                        ),
                                        name: report.closure.reopened_by ?? '',
                                        reason:
                                            report.closure.reopen_reason ?? '',
                                    })}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
            </ReportTemplate>

            {report.month && (
                <>
                    <FormDialog
                        open={dialog === 'close'}
                        onOpenChange={(open) => !open && setDialog(null)}
                        title={__('Close :month?', {
                            month: formatMonth(report.month),
                        })}
                        description={
                            pending > 0
                                ? __(
                                      ':count pending deposits are dated in this month. Once it is closed they can no longer be approved. Nothing dated in the month can be created or changed until it is reopened.',
                                      { count: pending },
                                  )
                                : __(
                                      'Nothing dated in this month can be created or changed until it is reopened.',
                                  )
                        }
                        action={close({ query: { month: report.month } })}
                        submitLabel={__('Close month')}
                    />
                    <FormDialog
                        open={dialog === 'reopen'}
                        onOpenChange={(open) => !open && setDialog(null)}
                        title={__('Reopen :month?', {
                            month: formatMonth(report.month),
                        })}
                        description={__(
                            'Changes become possible again for this month. The reason is kept in the audit log.',
                        )}
                        action={reopen({ query: { month: report.month } })}
                        field={{
                            type: 'reason',
                            name: 'reason',
                            label: __('Reason'),
                        }}
                        submitLabel={__('Reopen month')}
                    />
                </>
            )}
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
