import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Wallet } from 'lucide-react';
import { DepositStatusBadge } from '@/components/deposit-status-badge';
import { PurchaseStatusBadge } from '@/components/purchase-status-badge';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { SignedMoney } from '@/components/signed-money';
import { StatCard } from '@/components/stat-card';
import { DashboardTemplate } from '@/components/templates/dashboard-template';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { MonthPicker } from '@/components/ui/month-picker';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { dashboard } from '@/routes';
import { index as approvals } from '@/routes/approvals';
import { index as deposits, create as createDeposit } from '@/routes/deposits';
import {
    create as createPurchase,
    index as purchases,
    show as showPurchase,
} from '@/routes/grocery';
import { index as payables } from '@/routes/payables';
import type { DashboardProps, MonthlyMemberRow } from '@/types';

const monthName = new Intl.DateTimeFormat('en-GB', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

function formatMonth(month: string): string {
    return monthName.format(new Date(`${month}-01T00:00:00Z`));
}

export default function Dashboard({
    month,
    currentMonth,
    serverNow,
    memberStatus,
    summary,
    report,
    owed,
    recentDeposits,
    recentPurchases,
    queues,
    can,
}: DashboardProps) {
    const { user } = usePage().props.auth;
    const { balance } = usePage().props.fund;
    const __ = useTranslate();

    const countMembers = (count: number) =>
        count === 1 ? __('1 member') : __(':count members', { count });

    const browse = (next: string) =>
        router.get(
            dashboard.url({ query: { month: next || undefined } }),
            {},
            { preserveScroll: true },
        );

    const stats = [
        ...(balance !== null
            ? [
                  <StatCard
                      key="balance"
                      label={__('Fund balance')}
                      value={formatMoney(balance)}
                  />,
              ]
            : []),
        ...(summary
            ? [
                  <StatCard
                      key="deposits"
                      label={__('Your deposits')}
                      value={formatMoney(summary.deposits)}
                      hint={formatMonth(month)}
                  />,
                  <StatCard
                      key="share"
                      label={__('Your share')}
                      value={formatMoney(summary.share)}
                      hint={
                          report
                              ? __(':members share :amount', {
                                    members: countMembers(
                                        report.active_members,
                                    ),
                                    amount: formatMoney(report.expenses),
                                })
                              : undefined
                      }
                  />,
                  <StatCard
                      key="due"
                      label={__('Due or credit')}
                      value={
                          <SignedMoney
                              value={summary.due_credit}
                              positiveLabel={__('credit')}
                              negativeLabel={__('due')}
                          />
                      }
                      hint={
                          summary.due_credit.startsWith('-')
                              ? __('Still to pay this month')
                              : __('Covered this month')
                      }
                  />,
              ]
            : []),
    ];

    const columns: ResponsiveColumn<MonthlyMemberRow>[] = [
        { key: 'name', header: __('Member'), cell: (row) => row.name },
        {
            key: 'deposits',
            header: __('Deposits'),
            align: 'right',
            cell: (row) => (
                <span className="tabular-nums">
                    {formatMoney(row.deposits)}
                </span>
            ),
        },
        {
            key: 'share',
            header: __('Share'),
            align: 'right',
            priority: 'low',
            cell: (row) => (
                <span className="tabular-nums">{formatMoney(row.share)}</span>
            ),
        },
        {
            key: 'due_credit',
            header: __('Due or credit'),
            align: 'right',
            cell: (row) => (
                <SignedMoney
                    value={row.due_credit}
                    positiveLabel={__('credit')}
                    negativeLabel={__('due')}
                />
            ),
        },
    ];

    const showQueues =
        queues.pendingDeposits !== null ||
        queues.pendingRegistrations !== null ||
        queues.openPayables !== null;

    return (
        <>
            <Head title={__('Dashboard')} />
            <DashboardTemplate
                header={{
                    title: __('Welcome, :name', { name: user.name }),
                    description:
                        memberStatus === 'inactive'
                            ? __(
                                  'You no longer live in the mess. You can still see your history.',
                              )
                            : undefined,
                    primaryAction: can.createDeposit
                        ? {
                              label: __('Submit deposit'),
                              href: createDeposit(),
                              icon: Wallet,
                          }
                        : undefined,
                    secondaryActions: can.createPurchase
                        ? [
                              {
                                  label: __('Add purchase'),
                                  href: createPurchase(),
                                  icon: Plus,
                              },
                          ]
                        : undefined,
                    children: (
                        <div className="flex flex-col gap-1">
                            <label
                                htmlFor="dashboard-month"
                                className="text-label"
                            >
                                {__('Month')}
                            </label>
                            <MonthPicker
                                id="dashboard-month"
                                value={month}
                                onValueChange={(next) =>
                                    browse(next === currentMonth ? '' : next)
                                }
                                clearable={false}
                                className="w-48"
                            />
                        </div>
                    ),
                }}
                stats={stats.length > 0 ? stats : undefined}
                aside={
                    <>
                        {owed !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-subhead">
                                        {__('Owed to you')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-1">
                                    <p className="text-money tabular-nums">
                                        {formatMoney(owed)}
                                    </p>
                                    <p className="text-small text-ink-muted">
                                        {__(
                                            'What the fund still owes you for purchases it could not cover or that you paid for yourself.',
                                        )}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                        {showQueues && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-subhead">
                                        {__('Waiting for you')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="flex flex-col divide-y divide-border">
                                        {queues.pendingDeposits !== null && (
                                            <QueueRow
                                                label={__('Pending deposits')}
                                                count={queues.pendingDeposits}
                                                href={approvals()}
                                            />
                                        )}
                                        {queues.pendingRegistrations !==
                                            null && (
                                            <QueueRow
                                                label={__(
                                                    'Pending registrations',
                                                )}
                                                count={
                                                    queues.pendingRegistrations
                                                }
                                                href={approvals()}
                                            />
                                        )}
                                        {queues.openPayables !== null && (
                                            <QueueRow
                                                label={__('Open payables')}
                                                count={queues.openPayables}
                                                href={payables()}
                                            />
                                        )}
                                    </ul>
                                </CardContent>
                            </Card>
                        )}
                    </>
                }
            >
                {report && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-subhead">
                                {__('Everyone in :month', {
                                    month: formatMonth(month),
                                })}
                            </CardTitle>
                            <p className="text-small text-ink-muted">
                                {__(
                                    'Expenses :expenses · :members · :share each',
                                    {
                                        expenses: formatMoney(report.expenses),
                                        members: countMembers(
                                            report.active_members,
                                        ),
                                        share: formatMoney(report.share),
                                    },
                                )}
                                {report.leftover !== '0.00' &&
                                    ` · ${__(':amount not divisible', {
                                        amount: formatMoney(report.leftover),
                                    })}`}
                            </p>
                        </CardHeader>
                        <CardContent>
                            {report.members.length === 0 ? (
                                <p className="text-small text-ink-muted">
                                    {__(
                                        'No members lived in the mess this month.',
                                    )}
                                </p>
                            ) : (
                                <ResponsiveTable
                                    caption={__('Monthly figures per member')}
                                    columns={columns}
                                    rows={report.members}
                                    rowKey={(row) => row.member_id}
                                    card={(row) => ({
                                        title: row.name,
                                        value: (
                                            <SignedMoney
                                                value={row.due_credit}
                                                positiveLabel={__('credit')}
                                                negativeLabel={__('due')}
                                            />
                                        ),
                                        meta: [
                                            __('Deposited :amount', {
                                                amount: formatMoney(
                                                    row.deposits,
                                                ),
                                            }),
                                        ],
                                    })}
                                />
                            )}
                        </CardContent>
                    </Card>
                )}

                {memberStatus !== null && can.viewDeposits && (
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-subhead">
                                {__('Your latest deposits')}
                            </CardTitle>
                            <Link
                                href={deposits()}
                                className="text-small text-primary hover:underline"
                            >
                                {__('All deposits')}
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentDeposits.length === 0 ? (
                                <p className="text-small text-ink-muted">
                                    {__('No deposits yet.')}
                                </p>
                            ) : (
                                <ul className="flex flex-col divide-y divide-border">
                                    {recentDeposits.map((deposit) => (
                                        <li
                                            key={deposit.id}
                                            className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                        >
                                            <span className="flex min-w-0 flex-col gap-1">
                                                <span>
                                                    {formatBusinessDate(
                                                        deposit.deposit_date,
                                                    )}
                                                </span>
                                                <DepositStatusBadge
                                                    status={deposit.status}
                                                    label={deposit.status_label}
                                                    selfApproved={
                                                        deposit.is_self_approved
                                                    }
                                                />
                                                {deposit.rejection_reason && (
                                                    <span className="text-small text-ink-muted">
                                                        {__('Reason: :reason', {
                                                            reason: deposit.rejection_reason,
                                                        })}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="tabular-nums">
                                                {formatMoney(deposit.amount)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}

                {memberStatus !== null && can.viewPurchases && (
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle className="text-subhead">
                                {__('Your latest purchases')}
                            </CardTitle>
                            <Link
                                href={purchases()}
                                className="text-small text-primary hover:underline"
                            >
                                {__('All purchases')}
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentPurchases.length === 0 ? (
                                <p className="text-small text-ink-muted">
                                    {__('No purchases yet.')}
                                </p>
                            ) : (
                                <ul className="flex flex-col divide-y divide-border">
                                    {recentPurchases.map((purchase) => (
                                        <li
                                            key={purchase.id}
                                            className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                        >
                                            <span className="flex min-w-0 flex-col gap-1">
                                                <Link
                                                    href={showPurchase(
                                                        purchase.id,
                                                    )}
                                                    className="text-ink hover:text-primary"
                                                >
                                                    {formatBusinessDate(
                                                        purchase.purchase_date,
                                                    )}
                                                </Link>
                                                <span className="truncate text-small text-ink-muted">
                                                    {purchase.item_names.join(
                                                        ', ',
                                                    )}
                                                </span>
                                                <PurchaseStatusBadge
                                                    purchase={purchase}
                                                    serverNow={serverNow}
                                                />
                                            </span>
                                            <span className="tabular-nums">
                                                {formatMoney(
                                                    purchase.grand_total,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}
            </DashboardTemplate>
        </>
    );
}

function QueueRow({
    label,
    count,
    href,
}: {
    label: string;
    count: number;
    href: ReturnType<typeof approvals>;
}) {
    return (
        <li className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
            <Link href={href} className="text-ink hover:text-primary">
                {label}
            </Link>
            <Badge variant={count > 0 ? 'warning' : 'neutral'}>{count}</Badge>
        </li>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
