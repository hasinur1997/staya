import { Head, useForm, setLayoutProps } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { DetailTemplate } from '@/components/templates/detail-template';
import { FormTemplate } from '@/components/templates/form-template';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { dashboard } from '@/routes';
import { edit, update } from '@/routes/mess-settings';
import type { MessSettings } from '@/types';

type Props = {
    settings: MessSettings;
    /** Today in Asia/Dhaka (Y-m-d); the latest allowed fund start date. */
    today: string;
    can: { update: boolean; changeOpeningBalance: boolean };
};

export default function MessSettingsEdit({ settings, today, can }: Props) {
    const __ = useTranslate();
    const form = useForm({
        mess_name: settings.mess_name ?? '',
        mess_address: settings.mess_address ?? '',
        opening_balance: settings.opening_balance,
        fund_start_date: settings.fund_start_date ?? '',
        grocery_edit_window_minutes: String(
            settings.grocery_edit_window_minutes,
        ),
        members_can_view_balance: settings.members_can_view_balance,
        max_upload_kb: String(settings.max_upload_kb),
    });

    setLayoutProps({
        breadcrumbs: [{ title: __('Settings'), href: edit() }],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(update(), { preserveScroll: true });
    };

    const header = {
        title: __('Mess settings'),
        description:
            settings.updated_at && settings.updated_by
                ? __('Last changed :time by :name', {
                      time: formatDateTime(settings.updated_at),
                      name: settings.updated_by,
                  })
                : __(
                      'Mess-wide rules for the fund, grocery purchases and uploads',
                  ),
    };

    const fundLockedHelp = __(
        'Locked because the ledger has activity. Correct the fund with a manual adjustment.',
    );

    const fields = (
        <>
            <Fieldset legend={__('Mess')}>
                <Field
                    id="mess_name"
                    label={__('Mess name')}
                    error={form.errors.mess_name}
                >
                    <Input
                        id="mess_name"
                        value={form.data.mess_name}
                        onChange={(event) =>
                            form.setData('mess_name', event.target.value)
                        }
                        maxLength={150}
                        autoComplete="off"
                    />
                </Field>
                <Field
                    id="mess_address"
                    label={__('Address')}
                    error={form.errors.mess_address}
                >
                    <Textarea
                        id="mess_address"
                        value={form.data.mess_address}
                        onChange={(event) =>
                            form.setData('mess_address', event.target.value)
                        }
                        rows={3}
                    />
                </Field>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        id="currency"
                        label={__('Currency')}
                        help={__('Fixed in this version.')}
                    >
                        <Input
                            id="currency"
                            value={`${settings.currency} (৳)`}
                            readOnly
                        />
                    </Field>
                    <Field
                        id="timezone"
                        label={__('Timezone')}
                        help={__('Fixed in this version.')}
                    >
                        <Input
                            id="timezone"
                            value={settings.timezone}
                            readOnly
                        />
                    </Field>
                </div>
            </Fieldset>

            <Fieldset legend={__('Fund')}>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        id="opening_balance"
                        label={__('Opening balance (৳)')}
                        help={
                            can.changeOpeningBalance
                                ? __('Currently :amount.', {
                                      amount: formatMoney(
                                          settings.opening_balance,
                                      ),
                                  })
                                : fundLockedHelp
                        }
                        error={form.errors.opening_balance}
                    >
                        <Input
                            id="opening_balance"
                            inputMode="decimal"
                            value={form.data.opening_balance}
                            onChange={(event) =>
                                form.setData(
                                    'opening_balance',
                                    event.target.value,
                                )
                            }
                            className="text-right tabular-nums"
                            readOnly={!can.changeOpeningBalance}
                            required
                        />
                    </Field>
                    <Field
                        id="fund_start_date"
                        label={__('Fund start date')}
                        help={
                            can.changeOpeningBalance
                                ? __(
                                      'No purchase or deposit can be dated before it.',
                                  )
                                : fundLockedHelp
                        }
                        error={form.errors.fund_start_date}
                    >
                        <Input
                            id="fund_start_date"
                            type="date"
                            max={today}
                            readOnly={!can.changeOpeningBalance}
                            value={form.data.fund_start_date}
                            onChange={(event) =>
                                form.setData(
                                    'fund_start_date',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                </div>
            </Fieldset>

            <Fieldset legend={__('Rules')}>
                <div className="md:max-w-xs">
                    <Field
                        id="grocery_edit_window_minutes"
                        label={__('Grocery edit window (minutes)')}
                        help={__(
                            'How long a member can edit or delete their own purchase.',
                        )}
                        error={form.errors.grocery_edit_window_minutes}
                    >
                        <Input
                            id="grocery_edit_window_minutes"
                            type="number"
                            min={1}
                            max={1440}
                            value={form.data.grocery_edit_window_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'grocery_edit_window_minutes',
                                    event.target.value,
                                )
                            }
                            required
                        />
                    </Field>
                </div>
                <div className="flex items-start gap-3">
                    <Checkbox
                        id="members_can_view_balance"
                        checked={form.data.members_can_view_balance}
                        onCheckedChange={(state) =>
                            form.setData(
                                'members_can_view_balance',
                                state === true,
                            )
                        }
                        className="mt-1"
                    />
                    <div className="flex min-w-0 flex-col gap-1">
                        <Label htmlFor="members_can_view_balance">
                            {__('Members can view the shared fund balance')}
                        </Label>
                        <InputError
                            message={form.errors.members_can_view_balance}
                        />
                    </div>
                </div>
            </Fieldset>

            <Fieldset legend={__('Uploads')}>
                <div className="md:max-w-xs">
                    <Field
                        id="max_upload_kb"
                        label={__('Max upload size (KB)')}
                        help={__('1024 KB is 1 MB.')}
                        error={form.errors.max_upload_kb}
                    >
                        <Input
                            id="max_upload_kb"
                            type="number"
                            min={100}
                            max={20480}
                            value={form.data.max_upload_kb}
                            onChange={(event) =>
                                form.setData(
                                    'max_upload_kb',
                                    event.target.value,
                                )
                            }
                            required
                        />
                    </Field>
                </div>
            </Fieldset>
        </>
    );

    return (
        <>
            <Head title={__('Mess settings')} />
            {can.update ? (
                <FormTemplate
                    header={header}
                    formId="mess-settings-form"
                    submitLabel={__('Save changes')}
                    processing={form.processing}
                    cancelHref={dashboard()}
                >
                    <form
                        id="mess-settings-form"
                        onSubmit={submit}
                        className="flex flex-col gap-4 md:gap-6"
                    >
                        {fields}
                    </form>
                </FormTemplate>
            ) : (
                <DetailTemplate header={header}>
                    <fieldset disabled className="flex flex-col gap-4 md:gap-6">
                        {fields}
                    </fieldset>
                </DetailTemplate>
            )}
        </>
    );
}

function Fieldset({
    legend,
    children,
}: {
    legend: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <fieldset className="flex flex-col gap-4 px-4">
                <legend className="mb-4 text-subhead">{legend}</legend>
                {children}
            </fieldset>
        </Card>
    );
}

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
