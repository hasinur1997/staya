import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { FormTemplate } from '@/components/templates/form-template';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/ledger';
import { create, store } from '@/routes/ledger/adjustments';
import type { LedgerDirectionValue } from '@/types';

type Props = {
    /** Today in Asia/Dhaka (Y-m-d); the latest allowed date. */
    today: string;
    /** Y-m-d; the earliest allowed date, once set. */
    fundStartDate: string | null;
};

export default function AdjustmentsCreate({ today, fundStartDate }: Props) {
    const __ = useTranslate();
    const { fund } = usePage().props;
    const form = useForm({
        direction: 'credit' as LedgerDirectionValue,
        amount: '',
        entry_date: today,
        reason: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(store(), { preserveScroll: true });
    };

    return (
        <>
            <Head title={__('New adjustment')} />
            <FormTemplate
                header={{
                    title: __('New adjustment'),
                    description: __(
                        'Corrects the fund balance. Adjustments are never shared among members as expenses.',
                    ),
                }}
                formId="adjustment-form"
                submitLabel={__('Post adjustment')}
                processing={form.processing}
                cancelHref={index()}
                aside={
                    <Card>
                        <CardHeader>
                            <CardTitle>{__('Before you post')}</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-small text-ink-muted">
                            {fund.balance !== null && (
                                <p>
                                    {__('The fund holds :amount now.', {
                                        amount: formatMoney(fund.balance),
                                    })}
                                </p>
                            )}
                            <p>
                                {__(
                                    'Taking money out cannot bring the fund below ৳ 0.00.',
                                )}
                            </p>
                            <p>
                                {__(
                                    'An adjustment cannot be edited or deleted once posted.',
                                )}
                            </p>
                        </CardContent>
                    </Card>
                }
            >
                <form
                    id="adjustment-form"
                    onSubmit={submit}
                    className="flex flex-col gap-4 md:gap-6"
                >
                    <Card>
                        <fieldset className="flex flex-col gap-4 px-4">
                            <legend className="mb-4 text-subhead">
                                {__('Adjustment')}
                            </legend>
                            <div className="flex flex-col gap-2">
                                <Label id="direction-label">
                                    {__('Direction')}
                                </Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    aria-labelledby="direction-label"
                                    value={form.data.direction}
                                    onValueChange={(value) =>
                                        value &&
                                        form.setData(
                                            'direction',
                                            value as LedgerDirectionValue,
                                        )
                                    }
                                    className="w-fit"
                                >
                                    <ToggleGroupItem value="credit">
                                        {__('Add to fund')}
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="debit">
                                        {__('Take from fund')}
                                    </ToggleGroupItem>
                                </ToggleGroup>
                                <InputError message={form.errors.direction} />
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    id="amount"
                                    label={__('Amount (৳)')}
                                    error={form.errors.amount}
                                >
                                    <Input
                                        id="amount"
                                        inputMode="decimal"
                                        value={form.data.amount}
                                        onChange={(event) =>
                                            form.setData(
                                                'amount',
                                                event.target.value,
                                            )
                                        }
                                        className="text-right tabular-nums"
                                        placeholder="0.00"
                                        required
                                    />
                                </Field>
                                <Field
                                    id="entry_date"
                                    label={__('Date')}
                                    help={
                                        fundStartDate
                                            ? __('Between :start and today.', {
                                                  start: formatBusinessDate(
                                                      fundStartDate,
                                                  ),
                                              })
                                            : __('Today or earlier.')
                                    }
                                    error={form.errors.entry_date}
                                >
                                    <Input
                                        id="entry_date"
                                        type="date"
                                        min={fundStartDate ?? undefined}
                                        max={today}
                                        value={form.data.entry_date}
                                        onChange={(event) =>
                                            form.setData(
                                                'entry_date',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                </Field>
                            </div>
                            <Field
                                id="reason"
                                label={__('Reason')}
                                help={__(
                                    'Shown in the ledger and the audit log.',
                                )}
                                error={form.errors.reason}
                            >
                                <Textarea
                                    id="reason"
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={1000}
                                    rows={3}
                                    required
                                />
                            </Field>
                        </fieldset>
                    </Card>
                </form>
            </FormTemplate>
        </>
    );
}

AdjustmentsCreate.layout = {
    breadcrumbs: [
        { title: 'Ledger', href: index() },
        { title: 'New adjustment', href: create() },
    ],
};

function Field({
    id,
    label,
    help,
    error,
    children,
}: {
    id: string;
    label: string;
    help?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {help && <p className="text-small text-ink-muted">{help}</p>}
            <InputError message={error} />
        </div>
    );
}
