import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Settings2, User } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { DetailTemplate } from '@/components/templates/detail-template';
import { FormTemplate } from '@/components/templates/form-template';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBusinessDate, formatMonth } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { dashboard } from '@/routes';
import { index, mine, store } from '@/routes/meals';
import { index as mealTypes } from '@/routes/meal-types';
import type { MealDayMember, MealMonthFigures, MealTypeOption } from '@/types';

type Props = {
    date: string;
    today: string;
    types: MealTypeOption[];
    members: MealDayMember[];
    dayTotals: Record<string, number>;
    month: MealMonthFigures;
    monthClosed: boolean;
    maxPerType: number;
    can: { record: boolean; viewOwn: boolean; manageTypes: boolean };
};

export default function MealsIndex(props: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Meals', href: index() }],
    });

    return <MealsDay key={props.date} {...props} />;
}

function MealsDay({
    date,
    today,
    types,
    members,
    dayTotals,
    month,
    monthClosed,
    maxPerType,
    can,
}: Props) {
    const __ = useTranslate();
    const editable = can.record && !monthClosed;

    const form = useForm<{
        date: string;
        entries: Record<string, Record<string, string>>;
    }>({
        date,
        entries: Object.fromEntries(
            members.map((member) => [
                String(member.id),
                Object.fromEntries(
                    types
                        .filter(
                            (type) =>
                                type.is_active || type.id in member.counts,
                        )
                        .map((type) => [
                            String(type.id),
                            String(member.counts[String(type.id)] ?? 0),
                        ]),
                ),
            ]),
        ),
    });

    const setCount = (memberId: number, typeId: number, value: string) =>
        form.setData('entries', {
            ...form.data.entries,
            [memberId]: { ...form.data.entries[memberId], [typeId]: value },
        });

    const fillColumn = (typeId: number, value: string) =>
        form.setData(
            'entries',
            Object.fromEntries(
                Object.entries(form.data.entries).map(([id, counts]) => [
                    id,
                    { ...counts, [typeId]: value },
                ]),
            ),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), { preserveScroll: true });
    };

    const goTo = (next: string) =>
        next &&
        router.get(
            index.url({ query: { date: next } }),
            {},
            { preserveScroll: true },
        );

    const total = (typeId: number) =>
        Object.values(form.data.entries).reduce(
            (sum, counts) =>
                sum + (Number.parseInt(counts[typeId] ?? '0', 10) || 0),
            0,
        );

    const columns = `minmax(0, 1fr) repeat(${types.length}, minmax(0, 7rem))`;

    const grid = (
        <Card>
            <CardHeader>
                <CardTitle className="text-subhead">
                    {formatBusinessDate(date)}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {monthClosed && (
                    <Alert>
                        <AlertDescription>
                            {__(
                                'This month is closed, so meals cannot be changed. Reopen the month from Reports first.',
                            )}
                        </AlertDescription>
                    </Alert>
                )}
                {members.length === 0 ? (
                    <p className="text-ink-muted">
                        {__('No member lived in the mess on this day.')}
                    </p>
                ) : (
                    <div className="flex flex-col">
                        <div
                            className="hidden items-end gap-3 border-b pb-2 text-small text-ink-muted md:grid"
                            style={{ gridTemplateColumns: columns }}
                        >
                            <span>{__('Member')}</span>
                            {types.map((type) => (
                                <span
                                    key={type.id}
                                    className="flex flex-col gap-1"
                                >
                                    <span className="text-label text-ink">
                                        {type.name}
                                    </span>
                                    {editable && type.is_active && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                fillColumn(type.id, '1')
                                            }
                                        >
                                            {__('Everyone 1')}
                                        </Button>
                                    )}
                                </span>
                            ))}
                        </div>
                        {members.map((member) => (
                            <div
                                key={member.id}
                                className="flex flex-col gap-2 border-b py-3 last:border-0 md:grid md:items-center md:gap-3"
                                style={{ gridTemplateColumns: columns }}
                            >
                                <span className="text-label text-ink">
                                    {member.name}
                                </span>
                                {types.map((type) => {
                                    const id = `meal-${member.id}-${type.id}`;
                                    const locked = !editable || !type.is_active;

                                    return (
                                        <div
                                            key={type.id}
                                            className="flex items-center justify-between gap-3 md:block"
                                        >
                                            <Label
                                                htmlFor={id}
                                                className="md:sr-only"
                                            >
                                                {type.name}
                                            </Label>
                                            <Input
                                                id={id}
                                                type="number"
                                                inputMode="numeric"
                                                min={0}
                                                max={maxPerType}
                                                value={
                                                    form.data.entries[
                                                        member.id
                                                    ]?.[type.id] ?? '0'
                                                }
                                                disabled={locked}
                                                onChange={(event) =>
                                                    setCount(
                                                        member.id,
                                                        type.id,
                                                        event.target.value,
                                                    )
                                                }
                                                className="w-24 text-right tabular-nums md:w-full"
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        ))}
                        <div
                            className="flex flex-col gap-1 pt-3 md:grid md:items-center md:gap-3"
                            style={{ gridTemplateColumns: columns }}
                        >
                            <span className="text-label text-ink">
                                {__('Total')}
                            </span>
                            {types.map((type) => (
                                <span
                                    key={type.id}
                                    className="text-label text-ink tabular-nums md:text-right"
                                >
                                    <span className="text-small text-ink-muted md:hidden">
                                        {type.name}:{' '}
                                    </span>
                                    {editable
                                        ? total(type.id)
                                        : (dayTotals[type.id] ?? 0)}
                                </span>
                            ))}
                        </div>
                    </div>
                )}
                <InputError
                    message={
                        form.errors.entries ??
                        form.errors.date ??
                        Object.values(form.errors)[0]
                    }
                />
            </CardContent>
        </Card>
    );

    const picker = (
        <div className="flex flex-col gap-1">
            <Label htmlFor="meal-date">{__('Day')}</Label>
            <DatePicker
                id="meal-date"
                value={date}
                max={today}
                onValueChange={goTo}
                clearable={false}
                className="w-48"
            />
        </div>
    );

    const summary = (
        <Card>
            <CardHeader>
                <CardTitle className="text-subhead">
                    {formatMonth(date.slice(0, 7))}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-2 text-small">
                <p className="flex justify-between gap-3">
                    <span className="text-ink-muted">{__('Meals so far')}</span>
                    <span className="tabular-nums">{month.meals}</span>
                </p>
                <p className="flex justify-between gap-3">
                    <span className="text-ink-muted">
                        {__('Grocery expenses')}
                    </span>
                    <span className="tabular-nums">
                        {formatMoney(month.expenses)}
                    </span>
                </p>
                <p className="flex justify-between gap-3 text-label">
                    <span>{__('Cost per meal')}</span>
                    <span className="tabular-nums">
                        {month.rate !== null
                            ? formatMoney(month.rate)
                            : __('No meals yet')}
                    </span>
                </p>
                <p className="text-ink-muted">
                    {month.split === 'meals'
                        ? __('Members pay for the meals they had.')
                        : __(
                              'Costs are shared equally now. Change this in settings.',
                          )}
                </p>
            </CardContent>
        </Card>
    );

    const header = {
        title: __('Meals'),
        description: __(
            'Enter how many meals each member had on a day. Costs are divided by the meal count.',
        ),
        secondaryActions: [
            ...(can.viewOwn
                ? [{ label: __('My meals'), href: mine(), icon: User }]
                : []),
            ...(can.manageTypes
                ? [
                      {
                          label: __('Meal types'),
                          href: mealTypes(),
                          icon: Settings2,
                      },
                  ]
                : []),
        ],
        children: picker,
    };

    return (
        <>
            <Head title={__('Meals')} />
            {editable ? (
                <FormTemplate
                    header={header}
                    formId="meals-form"
                    submitLabel={__('Save meals')}
                    processing={form.processing}
                    cancelHref={dashboard()}
                    aside={summary}
                >
                    <form id="meals-form" onSubmit={submit}>
                        {grid}
                    </form>
                </FormTemplate>
            ) : (
                <DetailTemplate header={header} aside={summary}>
                    {grid}
                </DetailTemplate>
            )}
        </>
    );
}
