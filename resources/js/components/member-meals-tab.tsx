import { router } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { StatCard } from '@/components/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { MonthPicker } from '@/components/ui/month-picker';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatBusinessDate, formatMonth } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { index as mealsIndex } from '@/routes/meals';
import { show } from '@/routes/members';
import type { MemberMeals } from '@/types';

/** Friday and Saturday are the weekend in Bangladesh. */
const isWeekend = (weekday: number) => weekday === 5 || weekday === 6;

/** One member's meals for a month: a row per day, a column per meal type. */
export function MemberMealsTab({
    memberId,
    meals,
}: {
    memberId: number;
    meals: MemberMeals;
}) {
    const __ = useTranslate();

    const go = (month: string) =>
        router.get(
            show.url(memberId, {
                query: {
                    tab: 'meals',
                    month: month !== meals.currentMonth ? month : undefined,
                },
            }),
            {},
            { preserveScroll: true },
        );

    return (
        <div className="flex flex-col gap-4 md:gap-6">
            <div className="grid gap-4 md:grid-cols-4">
                <div className="flex flex-col gap-1">
                    <Label htmlFor="member-meal-month">{__('Month')}</Label>
                    <MonthPicker
                        id="member-meal-month"
                        value={meals.month}
                        onValueChange={(next) => next && go(next)}
                        clearable={false}
                    />
                </div>
                <StatCard
                    label={__('Meals this month')}
                    value={String(meals.total)}
                    hint={__(':count of :all in the mess', {
                        count: meals.total,
                        all: meals.figures.total_meals,
                    })}
                />
                <StatCard
                    label={__('Cost per meal')}
                    value={
                        meals.figures.rate !== null
                            ? formatMoney(meals.figures.rate)
                            : '—'
                    }
                />
                <StatCard
                    label={__('Cost of their meals')}
                    value={
                        meals.figures.cost !== null
                            ? formatMoney(meals.figures.cost)
                            : '—'
                    }
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-title">
                        {__('Meals in :month', {
                            month: formatMonth(meals.month),
                        })}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {meals.types.length === 0 ? (
                        <p className="text-caption text-ink-muted">
                            {__('No meal types yet.')}
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{__('Day')}</TableHead>
                                    {meals.types.map((type) => (
                                        <TableHead
                                            key={type.id}
                                            className="text-center"
                                        >
                                            {type.name}
                                        </TableHead>
                                    ))}
                                    <TableHead className="text-right">
                                        {__('Total')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {meals.days.map((day) => (
                                    <TableRow
                                        key={day.date}
                                        className={cn(
                                            isWeekend(day.weekday) &&
                                                'bg-surface-raised',
                                        )}
                                    >
                                        <TableCell className="whitespace-nowrap">
                                            {meals.can.record ? (
                                                <Link
                                                    href={mealsIndex.url({
                                                        query: {
                                                            date: day.date,
                                                        },
                                                    })}
                                                    className="text-primary hover:underline"
                                                >
                                                    {formatBusinessDate(
                                                        day.date,
                                                    )}
                                                </Link>
                                            ) : (
                                                formatBusinessDate(day.date)
                                            )}
                                        </TableCell>
                                        {meals.types.map((type) => (
                                            <TableCell
                                                key={type.id}
                                                className={cn(
                                                    'text-center tabular-nums',
                                                    !day.counts[type.id] &&
                                                        'text-ink-muted',
                                                )}
                                            >
                                                {day.counts[type.id] ?? '·'}
                                            </TableCell>
                                        ))}
                                        <TableCell className="text-right tabular-nums">
                                            {day.total || '·'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell>{__('Total')}</TableCell>
                                    {meals.types.map((type) => (
                                        <TableCell
                                            key={type.id}
                                            className="text-center tabular-nums"
                                        >
                                            {meals.typeTotals[type.id] ?? 0}
                                        </TableCell>
                                    ))}
                                    <TableCell className="text-right tabular-nums">
                                        {meals.total}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
