import { Head, Link, router, usePage } from '@inertiajs/react';
import { MemberName } from '@/components/member-name';
import { Plus, Wallet } from 'lucide-react';
import {
    AreaLine,
    ChartCard,
    Columns,
    DivergingBars,
    EmptyChart,
    GroupedBars,
    Legend,
    RankedBars,
} from '@/components/charts';
import { DepositStatusBadge } from '@/components/deposit-status-badge';
import { PurchaseStatusBadge } from '@/components/purchase-status-badge';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { SignedMoney } from '@/components/signed-money';
import { StatCard } from '@/components/stat-card';
import { PageGrid } from '@/components/templates/page-grid';
import { DashboardTemplate } from '@/components/templates/dashboard-template';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { MonthPicker } from '@/components/ui/month-picker';
import { formatBusinessDate, formatMonth } from '@/lib/date';
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

export default function Dashboard({
    month,
    currentMonth,
    serverNow,
    memberStatus,
    summary,
    report,
    insights,
    myTrend,
    account,
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

    const glance = [
        ...(account?.deposited
            ? [
                  <StatCard
                      key="deposited"
                      label={__('You have deposited')}
                      value={formatMoney(account.deposited.total)}
                      hint={__('Total so far. :amount in :month', {
                          amount: formatMoney(account.deposited.month),
                          month: formatMonth(month),
                      })}
                  />,
              ]
            : []),
        ...(account?.bajar
            ? [
                  <StatCard
                      key="bajar"
                      label={__('You spent on bajar')}
                      value={formatMoney(account.bajar.total)}
                      hint={__('Total so far. :amount in :month', {
                          amount: formatMoney(account.bajar.month),
                          month: formatMonth(month),
                      })}
                  />,
              ]
            : []),
        ...(balance !== null && !report
            ? [
                  <StatCard
                      key="balance"
                      label={__('Fund balance now')}
                      value={formatMoney(balance)}
                      hint={__('Money in the mess fund today')}
                  />,
              ]
            : []),
    ];

    const stats = summary
        ? [
              <StatCard
                  key="share"
                  label={
                      report?.split === 'meals'
                          ? __('Your meal cost')
                          : __('Your share')
                  }
                  value={formatMoney(summary.share)}
                  hint={
                      report?.split === 'meals'
                          ? __(':meals of :total meals', {
                                meals: summary.meals,
                                total: report.total_meals,
                            })
                          : report
                            ? __(':members share :amount', {
                                  members: countMembers(report.active_members),
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
        : [];

    const columns: ResponsiveColumn<MonthlyMemberRow>[] = [
        {
            key: 'name',
            header: __('Member'),
            cell: (row) => <MemberName id={row.member_id} name={row.name} />,
        },
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

    const shortMonth = (value: string) =>
        formatMonth(value)
            .replace(/\s\d{4}$/, '')
            .slice(0, 3);
    const previousMonth = (value: string) => {
        const [year, number] = value.split('-').map(Number);

        return number === 1
            ? `${year - 1}-12`
            : `${year}-${String(number - 1).padStart(2, '0')}`;
    };
    const against = (change: string) => (
        <span>
            <SignedMoney
                value={change}
                positiveLabel={__('up')}
                negativeLabel={__('down')}
            />{' '}
            {__('from :month', { month: formatMonth(previousMonth(month)) })}
        </span>
    );

    const overview =
        report && insights
            ? [
                  <StatCard
                      key="fund"
                      label={__('Fund balance now')}
                      value={balance !== null ? formatMoney(balance) : '—'}
                      hint={against(insights.changes.fund)}
                  />,
                  <StatCard
                      key="deposits"
                      label={__('Approved deposits')}
                      value={formatMoney(report.deposits)}
                      hint={against(insights.changes.deposits)}
                  />,
                  <StatCard
                      key="expenses"
                      label={__('Grocery expenses')}
                      value={formatMoney(report.expenses)}
                      hint={against(insights.changes.expenses)}
                  />,
                  <StatCard
                      key="rate"
                      label={
                          report.split === 'meals'
                              ? __('Cost per meal')
                              : __('Equal share')
                      }
                      value={formatMoney(report.share)}
                      hint={
                          report.split === 'meals'
                              ? __(':meals meals · :members', {
                                    meals: report.total_meals,
                                    members: countMembers(
                                        report.active_members,
                                    ),
                                })
                              : countMembers(report.active_members)
                      }
                  />,
              ]
            : undefined;

    const charts =
        report && insights ? (
            <div className="flex flex-col gap-4 md:gap-6">
                <PageGrid>
                    <div className="col-span-4 md:col-span-4 lg:col-span-6">
                        <ChartCard
                            title={__('Deposits and expenses')}
                            description={__('The last six months')}
                            legend={
                                <Legend
                                    items={[
                                        {
                                            label: __('Deposits'),
                                            className: 'bg-primary',
                                        },
                                        {
                                            label: __('Expenses'),
                                            className: 'bg-highlight',
                                        },
                                    ]}
                                />
                            }
                        >
                            <GroupedBars
                                aLabel={__('Deposits')}
                                bLabel={__('Expenses')}
                                data={insights.trend.map((row) => ({
                                    label: shortMonth(row.month),
                                    a: row.deposits,
                                    b: row.expenses,
                                    aText: formatMoney(row.deposits),
                                    bText: formatMoney(row.expenses),
                                }))}
                            />
                        </ChartCard>
                    </div>
                    <div className="col-span-4 md:col-span-4 lg:col-span-6">
                        <ChartCard
                            title={__('Fund balance')}
                            description={__('At the end of each month')}
                        >
                            <AreaLine
                                name={__('Fund balance')}
                                data={insights.trend.map((row) => ({
                                    label: shortMonth(row.month),
                                    value: row.fund_end,
                                    text: formatMoney(row.fund_end),
                                }))}
                            />
                        </ChartCard>
                    </div>
                </PageGrid>
                <PageGrid>
                    <div className="col-span-4 md:col-span-4 lg:col-span-6">
                        <ChartCard
                            title={__('Grocery spending by day')}
                            description={formatMonth(month)}
                        >
                            {insights.daily.every(
                                (day) => day.expenses === '0.00',
                            ) ? (
                                <EmptyChart>
                                    {__('No purchases this month.')}
                                </EmptyChart>
                            ) : (
                                <Columns
                                    name={__('Grocery spending by day')}
                                    data={insights.daily.map((day) => ({
                                        label: String(day.day),
                                        value: day.expenses,
                                        text: formatMoney(day.expenses),
                                    }))}
                                />
                            )}
                        </ChartCard>
                    </div>
                    <div className="col-span-4 md:col-span-4 lg:col-span-6">
                        <ChartCard
                            title={__('Meals by day')}
                            description={formatMonth(month)}
                        >
                            {report.total_meals === 0 ? (
                                <EmptyChart>
                                    {__('No meals recorded this month.')}
                                </EmptyChart>
                            ) : (
                                <Columns
                                    tone="highlight"
                                    whole
                                    name={__('Meals by day')}
                                    data={insights.daily.map((day) => ({
                                        label: String(day.day),
                                        value: String(day.meals),
                                        text: __(':count meals', {
                                            count: day.meals,
                                        }),
                                    }))}
                                />
                            )}
                        </ChartCard>
                    </div>
                </PageGrid>
                <PageGrid>
                    <div className="col-span-4 md:col-span-4 lg:col-span-6">
                        <ChartCard
                            title={__('Most bought items')}
                            description={__('By amount spent in :month', {
                                month: formatMonth(month),
                            })}
                        >
                            {insights.top_items.length === 0 ? (
                                <EmptyChart>
                                    {__('No purchases this month.')}
                                </EmptyChart>
                            ) : (
                                <RankedBars
                                    rows={insights.top_items.map((item) => ({
                                        label: item.name,
                                        value: item.total,
                                        text: formatMoney(item.total),
                                    }))}
                                />
                            )}
                        </ChartCard>
                    </div>
                    <div className="col-span-4 md:col-span-4 lg:col-span-6">
                        <ChartCard
                            title={__('Who owes and who is ahead')}
                            description={__('Deposits minus share, this month')}
                        >
                            {report.members.length === 0 ? (
                                <EmptyChart>
                                    {__(
                                        'No members lived in the mess this month.',
                                    )}
                                </EmptyChart>
                            ) : (
                                <DivergingBars
                                    dueLabel={__('Due')}
                                    creditLabel={__('Credit')}
                                    rows={report.members.map((row) => ({
                                        label: row.name,
                                        value: row.due_credit,
                                        text: (
                                            <SignedMoney
                                                value={row.due_credit}
                                                positiveLabel={__('credit')}
                                                negativeLabel={__('due')}
                                            />
                                        ),
                                    }))}
                                />
                            )}
                        </ChartCard>
                    </div>
                </PageGrid>
            </div>
        ) : myTrend && myTrend.length > 0 ? (
            <ChartCard
                title={__('Your deposits and share')}
                description={__('The last six months')}
                legend={
                    <Legend
                        items={[
                            {
                                label: __('Deposits'),
                                className: 'bg-primary',
                            },
                            { label: __('Share'), className: 'bg-highlight' },
                        ]}
                    />
                }
            >
                <GroupedBars
                    aLabel={__('Deposits')}
                    bLabel={__('Share')}
                    data={myTrend.map((row) => ({
                        label: shortMonth(row.month),
                        a: row.deposits,
                        b: row.share,
                        aText: formatMoney(row.deposits),
                        bText: formatMoney(row.share),
                    }))}
                />
            </ChartCard>
        ) : undefined;

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
                overview={overview}
                charts={charts}
                glance={glance.length > 0 ? glance : undefined}
                stats={stats.length > 0 ? stats : undefined}
                aside={
                    <>
                        {owed !== null && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-title">
                                        {__('Owed to you')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-1">
                                    <p className="stat-value">
                                        {formatMoney(owed)}
                                    </p>
                                    <p className="text-caption text-ink-muted">
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
                                    <CardTitle className="text-title">
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
                            <CardTitle className="text-title">
                                {__('Everyone in :month', {
                                    month: formatMonth(month),
                                })}
                            </CardTitle>
                            <p className="text-caption text-ink-muted">
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
                                <p className="text-caption text-ink-muted">
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
                                        title: (
                                            <MemberName
                                                id={row.member_id}
                                                name={row.name}
                                            />
                                        ),
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
                            <CardTitle className="text-title">
                                {__('Your latest deposits')}
                            </CardTitle>
                            <Link
                                href={deposits()}
                                className="text-body text-primary hover:underline"
                            >
                                {__('All deposits')}
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentDeposits.length === 0 ? (
                                <p className="text-caption text-ink-muted">
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
                                                    <span className="text-caption text-ink-muted">
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
                            <CardTitle className="text-title">
                                {__('Your latest purchases')}
                            </CardTitle>
                            <Link
                                href={purchases()}
                                className="text-body text-primary hover:underline"
                            >
                                {__('All purchases')}
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {recentPurchases.length === 0 ? (
                                <p className="text-caption text-ink-muted">
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
                                                <span className="truncate text-caption text-ink-muted">
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
