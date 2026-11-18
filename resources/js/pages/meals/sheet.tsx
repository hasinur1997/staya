import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { MemberAvatar } from '@/components/member-avatar';
import { MemberName } from '@/components/member-name';
import { Printer } from 'lucide-react';
import { StatCard } from '@/components/stat-card';
import { ReportTemplate } from '@/components/templates/report-template';
import { Card } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import { MonthPicker } from '@/components/ui/month-picker';
import { formatMonth } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index, sheet } from '@/routes/meals';
import type { MealSheetDay, MealSheetRow } from '@/types';

type Props = {
    month: string;
    currentMonth: string;
    typeId: number | null;
    types: { id: number; name: string }[];
    days: MealSheetDay[];
    rows: MealSheetRow[];
    total: number;
    figures: { expenses: string; total_meals: number; rate: string | null };
    can: { record: boolean };
};

const ALL = 'all';
const WEEKDAYS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
/** Friday and Saturday are the weekend in Bangladesh. */
const isWeekend = (weekday: number) => weekday === 5 || weekday === 6;

export default function MealSheet({
    month,
    currentMonth,
    typeId,
    types,
    days,
    rows,
    total,
    figures,
    can,
}: Props) {
    const __ = useTranslate();

    setLayoutProps({
        breadcrumbs: [
            { title: __('Meals'), href: index() },
            { title: __('Monthly sheet'), href: sheet() },
        ],
    });

    const go = (next: { month?: string; type?: number | null }) =>
        router.get(
            sheet.url({
                query: {
                    month:
                        (next.month ?? month) !== currentMonth
                            ? (next.month ?? month)
                            : undefined,
                    type:
                        (next.type === undefined ? typeId : next.type) ??
                        undefined,
                },
            }),
            {},
            { preserveScroll: true },
        );

    const stickyName =
        'sticky left-0 z-10 bg-background px-3 py-2 text-left whitespace-nowrap';
    const monthDate = (day: number) =>
        `${month}-${String(day).padStart(2, '0')}`;

    return (
        <>
            <Head title={__('Monthly meal sheet')} />
            <ReportTemplate
                header={{
                    overline: __('Meals'),
                    title: __('Meal sheet for :month', {
                        month: formatMonth(month),
                    }),
                    description: __(
                        'Meals each member had on each day. Open a day to change its counts.',
                    ),
                    secondaryActions: [
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
                            <Label htmlFor="sheet-month">{__('Month')}</Label>
                            <MonthPicker
                                id="sheet-month"
                                value={month}
                                onValueChange={(next) =>
                                    next && go({ month: next })
                                }
                                clearable={false}
                                className="w-48"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="sheet-type">
                                {__('Meal type')}
                            </Label>
                            <Combobox
                                id="sheet-type"
                                value={typeId ? String(typeId) : ALL}
                                onValueChange={(next) =>
                                    go({
                                        type:
                                            next === ALL ? null : Number(next),
                                    })
                                }
                                options={[
                                    { value: ALL, label: __('All meals') },
                                    ...types.map((type) => ({
                                        value: String(type.id),
                                        label: type.name,
                                    })),
                                ]}
                                searchPlaceholder={__('Search')}
                                emptyText={__('Nothing found.')}
                                className="w-48"
                            />
                        </div>
                    </>
                }
                summary={[
                    <StatCard
                        key="meals"
                        label={__('Meals this month')}
                        value={String(typeId ? total : figures.total_meals)}
                        hint={
                            typeId
                                ? __(':count in all meal types', {
                                      count: figures.total_meals,
                                  })
                                : undefined
                        }
                    />,
                    <StatCard
                        key="expenses"
                        label={__('Grocery expenses')}
                        value={formatMoney(figures.expenses)}
                    />,
                    <StatCard
                        key="rate"
                        label={__('Cost per meal')}
                        value={
                            figures.rate !== null
                                ? formatMoney(figures.rate)
                                : __('No meals yet')
                        }
                    />,
                ]}
            >
                <Card className="overflow-hidden p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-body tabular-nums">
                            <caption className="sr-only">
                                {__('Meals by member and day')}
                            </caption>
                            <thead>
                                <tr className="border-b text-ink-muted">
                                    <th scope="col" className={stickyName}>
                                        {__('Member')}
                                    </th>
                                    {days.map((day) => (
                                        <th
                                            key={day.day}
                                            scope="col"
                                            className={cn(
                                                'min-w-9 px-1 py-2 text-center font-normal',
                                                isWeekend(day.weekday) &&
                                                    'bg-muted',
                                            )}
                                        >
                                            <span className="block text-label text-ink">
                                                {day.day}
                                            </span>
                                            <span className="block text-caption">
                                                {WEEKDAYS[day.weekday]}
                                            </span>
                                        </th>
                                    ))}
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right text-label text-ink"
                                    >
                                        {__('Meals')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-2 text-right text-label text-ink"
                                    >
                                        {__('Meal cost')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr
                                        key={row.member_id}
                                        className="border-b last:border-0"
                                    >
                                        <th
                                            scope="row"
                                            className={cn(
                                                stickyName,
                                                'text-label font-normal',
                                            )}
                                        >
                                            <span className="flex items-center gap-3">
                                                <MemberAvatar
                                                    id={row.member_id}
                                                    name={row.name}
                                                    hasPhoto={row.has_photo}
                                                    version={row.media_version}
                                                    className="size-8"
                                                />
                                                <MemberName
                                                    id={row.member_id}
                                                    name={row.name}
                                                    className="font-semibold"
                                                />
                                            </span>
                                        </th>
                                        {days.map((day) => {
                                            const count = row.counts[day.day];

                                            return (
                                                <td
                                                    key={day.day}
                                                    className={cn(
                                                        'px-1 py-2 text-center',
                                                        isWeekend(
                                                            day.weekday,
                                                        ) && 'bg-muted',
                                                        !count &&
                                                            'text-ink-muted',
                                                    )}
                                                >
                                                    {count ? (
                                                        can.record ? (
                                                            <Link
                                                                href={index.url(
                                                                    {
                                                                        query: {
                                                                            date: monthDate(
                                                                                day.day,
                                                                            ),
                                                                        },
                                                                    },
                                                                )}
                                                                className="text-ink hover:text-primary"
                                                            >
                                                                {count}
                                                            </Link>
                                                        ) : (
                                                            count
                                                        )
                                                    ) : (
                                                        '·'
                                                    )}
                                                </td>
                                            );
                                        })}
                                        <td className="px-3 py-2 text-right text-label text-ink">
                                            {row.total}
                                        </td>
                                        <td className="px-3 py-2 text-right whitespace-nowrap">
                                            {row.cost !== null
                                                ? formatMoney(row.cost)
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr className="border-t text-label text-ink">
                                    <th scope="row" className={stickyName}>
                                        {__('Total')}
                                    </th>
                                    {days.map((day) => (
                                        <td
                                            key={day.day}
                                            className={cn(
                                                'px-1 py-2 text-center',
                                                isWeekend(day.weekday) &&
                                                    'bg-muted',
                                            )}
                                        >
                                            {day.total || '·'}
                                        </td>
                                    ))}
                                    <td className="px-3 py-2 text-right">
                                        {total}
                                    </td>
                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                        {!typeId && figures.total_meals > 0
                                            ? formatMoney(figures.expenses)
                                            : '—'}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </Card>
                {rows.length === 0 && (
                    <p className="text-ink-muted">
                        {__('No member lived in the mess in this month.')}
                    </p>
                )}
            </ReportTemplate>
        </>
    );
}
