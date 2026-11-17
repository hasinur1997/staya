import { Head, router, setLayoutProps } from '@inertiajs/react';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { StatCard } from '@/components/stat-card';
import { DashboardTemplate } from '@/components/templates/dashboard-template';
import { MonthPicker } from '@/components/ui/month-picker';
import { formatBusinessDate, formatMonth } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { mine } from '@/routes/meals';

type Day = { date: string; counts: Record<string, number> };

type Props = {
    month: string;
    currentMonth: string;
    types: { id: number; name: string }[];
    days: Day[];
    summary: {
        meals: number;
        total_meals: number;
        rate: string | null;
        meal_cost: string;
        expenses: string;
        split: 'equal' | 'meals';
        share: string;
    };
};

export default function MealsMine({
    month,
    currentMonth,
    types,
    days,
    summary,
}: Props) {
    const __ = useTranslate();

    setLayoutProps({ breadcrumbs: [{ title: __('My meals'), href: mine() }] });

    const rowTotal = (day: Day) =>
        Object.values(day.counts).reduce((sum, count) => sum + count, 0);

    const columns: ResponsiveColumn<Day>[] = [
        {
            key: 'date',
            header: __('Day'),
            cell: (day) => formatBusinessDate(day.date),
        },
        ...types.map((type) => ({
            key: `type-${type.id}`,
            header: type.name,
            align: 'right' as const,
            cell: (day: Day) => String(day.counts[type.id] ?? 0),
        })),
        {
            key: 'total',
            header: __('Total'),
            align: 'right' as const,
            cell: (day) => String(rowTotal(day)),
        },
    ];

    return (
        <>
            <Head title={__('My meals')} />
            <DashboardTemplate
                header={{
                    title: __('My meals'),
                    description: __(
                        'The meals recorded for you and what they cost.',
                    ),
                    children: (
                        <div className="flex flex-col gap-1">
                            <label htmlFor="meals-month" className="text-label">
                                {__('Month')}
                            </label>
                            <MonthPicker
                                id="meals-month"
                                value={month}
                                onValueChange={(next) =>
                                    router.get(
                                        mine.url({
                                            query: {
                                                month:
                                                    next &&
                                                    next !== currentMonth
                                                        ? next
                                                        : undefined,
                                            },
                                        }),
                                    )
                                }
                                clearable={false}
                                className="w-48"
                            />
                        </div>
                    ),
                }}
                glance={[
                    <StatCard
                        key="meals"
                        label={__('Your meals')}
                        value={String(summary.meals)}
                        hint={__(':total meals in the mess in :month', {
                            total: summary.total_meals,
                            month: formatMonth(month),
                        })}
                    />,
                    <StatCard
                        key="rate"
                        label={__('Cost per meal')}
                        value={
                            summary.rate !== null
                                ? formatMoney(summary.rate)
                                : __('No meals yet')
                        }
                        hint={__(':amount spent on grocery', {
                            amount: formatMoney(summary.expenses),
                        })}
                    />,
                    <StatCard
                        key="cost"
                        label={__('Your meal cost')}
                        value={formatMoney(summary.meal_cost)}
                        hint={
                            summary.split === 'meals'
                                ? __('This is your share this month')
                                : __(
                                      'Costs are shared equally now, so your share is :amount',
                                      { amount: formatMoney(summary.share) },
                                  )
                        }
                    />,
                ]}
            >
                {days.length === 0 ? (
                    <p className="text-ink-muted">
                        {__('No meals are recorded for you in this month.')}
                    </p>
                ) : (
                    <ResponsiveTable
                        caption={__('Your meals by day')}
                        columns={columns}
                        rows={days}
                        rowKey={(day) => day.date}
                        card={(day) => ({
                            title: formatBusinessDate(day.date),
                            value: String(rowTotal(day)),
                            meta: types.map(
                                (type) =>
                                    `${type.name} ${day.counts[type.id] ?? 0}`,
                            ),
                        })}
                    />
                )}
            </DashboardTemplate>
        </>
    );
}
