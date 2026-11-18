import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { MemberAvatar } from '@/components/member-avatar';
import { MemberName } from '@/components/member-name';
import { useState } from 'react';
import {
    ChevronLeft,
    ChevronRight,
    Minus,
    Pencil,
    Plus,
    Settings2,
    Table2,
    User,
} from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { DetailTemplate } from '@/components/templates/detail-template';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBusinessDate, formatMonth } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { index, mine, sheet, store } from '@/routes/meals';
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
    can: {
        record: boolean;
        viewOwn: boolean;
        manageTypes: boolean;
        viewSheet: boolean;
    };
};

export default function MealsIndex(props: Props) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Meals', href: index() }],
    });

    return <MealsDay key={props.date} {...props} />;
}

function shiftDate(date: string, days: number): string {
    const next = new Date(`${date}T00:00:00Z`);
    next.setUTCDate(next.getUTCDate() + days);

    return next.toISOString().slice(0, 10);
}

function clampCount(value: number, max: number): string {
    return String(Math.min(Math.max(value, 0), max));
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
    const [editing, setEditing] = useState(false);
    const active = editable && editing;

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

    const memberTotal = (memberId: number) =>
        Object.values(form.data.entries[memberId] ?? {}).reduce(
            (sum, value) => sum + (Number.parseInt(value, 10) || 0),
            0,
        );

    const step = (memberId: number, typeId: number, by: number) =>
        setCount(
            memberId,
            typeId,
            clampCount(
                (Number.parseInt(
                    form.data.entries[memberId]?.[typeId] ?? '0',
                    10,
                ) || 0) + by,
                maxPerType,
            ),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
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

    const columns = `minmax(0, 1fr) repeat(${types.length}, minmax(0, 10rem)) 4rem`;

    const grid = (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardTitle className="text-title">
                    {formatBusinessDate(date)}
                </CardTitle>
                {editable && !editing && (
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label={__('Edit meals')}
                        title={__('Edit meals')}
                        onClick={() => setEditing(true)}
                    >
                        <Pencil />
                    </Button>
                )}
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
                            className="hidden items-end gap-3 border-b pb-3 text-caption text-ink-muted md:grid"
                            style={{ gridTemplateColumns: columns }}
                        >
                            <span>{__('Member')}</span>
                            {types.map((type) => (
                                <span
                                    key={type.id}
                                    className="flex flex-col items-center gap-1"
                                >
                                    <span className="text-label text-ink">
                                        {type.name}
                                    </span>
                                    {active && type.is_active && (
                                        <span className="flex gap-1">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    fillColumn(type.id, '1')
                                                }
                                            >
                                                {__('All 1')}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    fillColumn(type.id, '0')
                                                }
                                            >
                                                {__('Clear')}
                                            </Button>
                                        </span>
                                    )}
                                </span>
                            ))}
                            <span className="text-right">{__('Total')}</span>
                        </div>
                        {members.map((member) => (
                            <div
                                key={member.id}
                                className="flex flex-col gap-2 border-b py-3 md:grid md:items-center md:gap-3"
                                style={{ gridTemplateColumns: columns }}
                            >
                                <span className="flex items-center gap-3">
                                    <MemberAvatar
                                        id={member.id}
                                        name={member.name}
                                        hasPhoto={member.has_photo}
                                        version={member.media_version}
                                    />
                                    <MemberName
                                        id={member.id}
                                        name={member.name}
                                        className="font-semibold"
                                    />
                                </span>
                                {types.map((type) => {
                                    const id = `meal-${member.id}-${type.id}`;
                                    const locked = !active || !type.is_active;

                                    const value =
                                        form.data.entries[member.id]?.[
                                            type.id
                                        ] ?? '0';

                                    return (
                                        <div
                                            key={type.id}
                                            className="flex items-center justify-between gap-3 md:justify-center"
                                        >
                                            <Label
                                                htmlFor={id}
                                                className="md:sr-only"
                                            >
                                                {type.name}
                                            </Label>
                                            {!active ? (
                                                <span
                                                    className={`w-24 text-center tabular-nums md:w-auto ${
                                                        Number(value) > 0
                                                            ? 'text-label text-ink'
                                                            : 'text-ink-muted'
                                                    }`}
                                                >
                                                    {value}
                                                </span>
                                            ) : (
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="w-8 px-0"
                                                        aria-label={`${__('Decrease')} ${type.name}`}
                                                        disabled={
                                                            locked ||
                                                            Number(value) <= 0
                                                        }
                                                        onClick={() =>
                                                            step(
                                                                member.id,
                                                                type.id,
                                                                -1,
                                                            )
                                                        }
                                                    >
                                                        <Minus />
                                                    </Button>
                                                    <Input
                                                        id={id}
                                                        type="number"
                                                        inputMode="numeric"
                                                        min={0}
                                                        max={maxPerType}
                                                        value={value}
                                                        disabled={locked}
                                                        onFocus={(event) =>
                                                            event.target.select()
                                                        }
                                                        onChange={(event) =>
                                                            setCount(
                                                                member.id,
                                                                type.id,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className={`h-8 w-14 [appearance:textfield] px-1 text-center tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none ${
                                                            Number(value) > 0
                                                                ? 'text-label text-ink'
                                                                : 'text-ink-muted'
                                                        }`}
                                                    />
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="w-8 px-0"
                                                        aria-label={`${__('Increase')} ${type.name}`}
                                                        disabled={
                                                            locked ||
                                                            Number(value) >=
                                                                maxPerType
                                                        }
                                                        onClick={() =>
                                                            step(
                                                                member.id,
                                                                type.id,
                                                                1,
                                                            )
                                                        }
                                                    >
                                                        <Plus />
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                                <span className="flex justify-between text-label text-ink tabular-nums md:block md:text-right">
                                    <span className="text-caption text-ink-muted md:hidden">
                                        {__('Total')}
                                    </span>
                                    {memberTotal(member.id)}
                                </span>
                            </div>
                        ))}
                        <div
                            className="flex flex-col gap-1 border-t pt-3 md:grid md:items-center md:gap-3"
                            style={{ gridTemplateColumns: columns }}
                        >
                            <span className="text-label text-ink">
                                {__('Total')}
                            </span>
                            {types.map((type) => (
                                <span
                                    key={type.id}
                                    className="text-label text-ink tabular-nums md:text-center"
                                >
                                    <span className="text-caption text-ink-muted md:hidden">
                                        {type.name}:{' '}
                                    </span>
                                    {editable
                                        ? total(type.id)
                                        : (dayTotals[type.id] ?? 0)}
                                </span>
                            ))}
                            <span className="text-label text-ink tabular-nums md:text-right">
                                {types.reduce(
                                    (sum, type) =>
                                        sum +
                                        (editable
                                            ? total(type.id)
                                            : (dayTotals[type.id] ?? 0)),
                                    0,
                                )}
                            </span>
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
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={__('Previous day')}
                    onClick={() => goTo(shiftDate(date, -1))}
                >
                    <ChevronLeft />
                </Button>
                <DatePicker
                    id="meal-date"
                    value={date}
                    max={today}
                    onValueChange={goTo}
                    clearable={false}
                    className="w-48"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={__('Next day')}
                    disabled={date >= today}
                    onClick={() => goTo(shiftDate(date, 1))}
                >
                    <ChevronRight />
                </Button>
                {date !== today && (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => goTo(today)}
                    >
                        {__('Today')}
                    </Button>
                )}
            </div>
        </div>
    );

    const summary = (
        <Card>
            <CardHeader>
                <CardTitle className="text-title">
                    {formatMonth(date.slice(0, 7))}
                </CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-2 text-body">
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
            ...(can.viewSheet
                ? [{ label: __('Monthly sheet'), href: sheet(), icon: Table2 }]
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
            <DetailTemplate header={header} aside={summary}>
                {editable ? (
                    <form
                        noValidate
                        id="meals-form"
                        onSubmit={submit}
                        className="flex flex-col gap-4"
                    >
                        {grid}
                        {active && (
                            <div className="shadow-md sticky bottom-4 flex items-center justify-between gap-3 rounded-lg border bg-background p-3 print:hidden">
                                <span className="text-caption text-ink-muted">
                                    {form.isDirty
                                        ? __('You have unsaved changes.')
                                        : __('Editing meals')}
                                </span>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => {
                                            form.reset();
                                            setEditing(false);
                                        }}
                                    >
                                        {form.isDirty
                                            ? __('Discard')
                                            : __('Cancel')}
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {__('Save meals')}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </form>
                ) : (
                    grid
                )}
            </DetailTemplate>
        </>
    );
}
