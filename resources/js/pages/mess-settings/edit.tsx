import { DatePicker } from '@/components/ui/date-picker';
import { Head, useForm, setLayoutProps } from '@inertiajs/react';
import { Info, Lock } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { ResetDataCard } from '@/components/reset-data-card';
import { SettingsSection } from '@/components/settings-section';
import { DetailTemplate } from '@/components/templates/detail-template';
import { FormTemplate } from '@/components/templates/form-template';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
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
    costSplits: { value: string; label: string }[];
    can: {
        update: boolean;
        resetData: boolean;
        changeOpeningBalance: boolean;
    };
};

export default function MessSettingsEdit({
    settings,
    today,
    costSplits,
    can,
}: Props) {
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
        cost_split: settings.cost_split as string,
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
            <SettingsSection
                title={__('Mess')}
                description={__('The name and address shown across the app.')}
            >
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
                <dl className="grid gap-4 border-t pt-4 md:grid-cols-2">
                    <FixedValue
                        label={__('Currency')}
                        value={`${settings.currency} (৳)`}
                    />
                    <FixedValue
                        label={__('Timezone')}
                        value={settings.timezone}
                    />
                </dl>
            </SettingsSection>

            <SettingsSection
                title={__('Fund')}
                description={__(
                    'Where the shared fund starts. These two lock once the ledger has activity.',
                )}
            >
                {!can.changeOpeningBalance && (
                    <p className="flex items-start gap-2 rounded-md bg-warning-soft p-3 text-body text-ink">
                        <Lock
                            className="mt-0.5 size-4 shrink-0 text-warning"
                            aria-hidden
                        />
                        {fundLockedHelp}
                    </p>
                )}
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
                                : undefined
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
                        required
                        help={
                            can.changeOpeningBalance
                                ? __(
                                      'No purchase or deposit can be dated before it.',
                                  )
                                : undefined
                        }
                        error={form.errors.fund_start_date}
                    >
                        <DatePicker
                            id="fund_start_date"
                            max={today}
                            disabled={!can.changeOpeningBalance}
                            value={form.data.fund_start_date}
                            onValueChange={(date) =>
                                form.setData('fund_start_date', date)
                            }
                            aria-invalid={Boolean(form.errors.fund_start_date)}
                        />
                    </Field>
                </div>
            </SettingsSection>

            <SettingsSection
                title={__('Rules')}
                description={__('What members can do and see.')}
            >
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
                <div className="flex items-start gap-3 border-t pt-4">
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
            </SettingsSection>

            <SettingsSection
                title={__('Cost sharing')}
                description={__(
                    'How the monthly cost is divided between members.',
                )}
            >
                <div className="md:max-w-md">
                    <Field
                        id="cost_split"
                        label={__('How the monthly cost is shared')}
                        help={
                            form.data.cost_split === 'meals'
                                ? __(
                                      'Total cost is divided by the total meals; each member pays for their own meals.',
                                  )
                                : __(
                                      'Total cost is divided equally between every member who lived in the mess.',
                                  )
                        }
                        error={form.errors.cost_split}
                    >
                        <Combobox
                            id="cost_split"
                            value={form.data.cost_split}
                            onValueChange={(value) =>
                                form.setData('cost_split', value)
                            }
                            options={costSplits}
                            searchPlaceholder={__('Search')}
                            emptyText={__('Nothing found.')}
                        />
                    </Field>
                </div>
                <p className="flex items-start gap-2 rounded-md bg-surface-raised p-3 text-caption text-ink-muted">
                    <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
                    {__(
                        'A closed month keeps the setting it was closed with. Changing this affects open months only.',
                    )}
                </p>
            </SettingsSection>

            <SettingsSection
                title={__('Uploads')}
                description={__('Limits for receipts, photos and documents.')}
            >
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
            </SettingsSection>
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
                    wide
                >
                    <form
                        noValidate
                        id="mess-settings-form"
                        onSubmit={submit}
                        className="flex flex-col gap-4 md:gap-6"
                    >
                        {fields}
                    </form>
                    {can.resetData && <ResetDataCard />}
                </FormTemplate>
            ) : (
                <DetailTemplate header={header}>
                    <fieldset disabled className="flex flex-col gap-4 md:gap-6">
                        {fields}
                    </fieldset>
                    {can.resetData && <ResetDataCard />}
                </DetailTemplate>
            )}
        </>
    );
}

/** A read-only value that cannot be changed in this version. */
function FixedValue({ label, value }: { label: string; value: string }) {
    const __ = useTranslate();

    return (
        <div className="flex flex-col gap-1">
            <dt className="text-label text-ink-muted">{label}</dt>
            <dd className="flex items-center gap-2 text-body text-ink">
                {value}
                <span className="badge-text rounded-sm bg-surface-raised px-2 py-0.5 text-ink-muted">
                    {__('Fixed')}
                </span>
            </dd>
        </div>
    );
}

function Field({
    id,
    label,
    help,
    error,
    required,
    children,
}: {
    id: string;
    label: string;
    help?: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id} required={required}>
                {label}
            </Label>
            {children}
            {help && <p className="text-caption text-ink-muted">{help}</p>}
            <InputError message={error} />
        </div>
    );
}
