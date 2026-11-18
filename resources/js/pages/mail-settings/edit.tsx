import { Head, router, useForm, setLayoutProps } from '@inertiajs/react';
import { Info } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { SettingsSection } from '@/components/settings-section';
import { FormTemplate } from '@/components/templates/form-template';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { dashboard } from '@/routes';
import { edit, test, update } from '@/routes/mail-settings';
import type { MailSettings } from '@/types';

type Props = {
    settings: MailSettings;
    encryptions: { value: string; label: string }[];
    /** Whether the app currently sends real email, from here or from .env. */
    delivering: boolean;
    /** The signed-in user's email: where the test message goes. */
    testRecipient: string;
};

export default function MailSettingsEdit({
    settings,
    encryptions,
    delivering,
    testRecipient,
}: Props) {
    const __ = useTranslate();
    const form = useForm({
        enabled: settings.enabled,
        host: settings.host ?? '',
        port: String(settings.port),
        encryption: settings.encryption as string,
        username: settings.username ?? '',
        password: '',
        from_address: settings.from_address ?? '',
        from_name: settings.from_name ?? '',
    });

    setLayoutProps({
        breadcrumbs: [{ title: __('Email'), href: edit() }],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(update(), {
            preserveScroll: true,
            onSuccess: () => form.setData('password', ''),
        });
    };

    const sendTest = () =>
        router.post(test.url(), {}, { preserveScroll: true });

    const header = {
        title: __('Email'),
        description:
            settings.updated_at && settings.updated_by
                ? __('Last changed :time by :name', {
                      time: formatDateTime(settings.updated_at),
                      name: settings.updated_by,
                  })
                : __(
                      'How the app sends invitations, password resets and other email',
                  ),
    };

    return (
        <>
            <Head title={__('Email')} />
            <FormTemplate
                header={header}
                formId="mail-settings-form"
                submitLabel={__('Save changes')}
                processing={form.processing}
                cancelHref={dashboard()}
                wide
            >
                <form
                    noValidate
                    id="mail-settings-form"
                    onSubmit={submit}
                    className="flex flex-col gap-4 md:gap-6"
                >
                    <SettingsSection
                        title={__('Sending')}
                        description={__(
                            'Turn this on to send real email through your own mail server. While it is off, the server configuration is used.',
                        )}
                    >
                        <div className="flex items-start gap-3">
                            <Checkbox
                                id="enabled"
                                checked={form.data.enabled}
                                onCheckedChange={(state) =>
                                    form.setData('enabled', state === true)
                                }
                                className="mt-1"
                            />
                            <div className="flex min-w-0 flex-col gap-1">
                                <Label htmlFor="enabled">
                                    {__('Send email with these settings')}
                                </Label>
                                <InputError message={form.errors.enabled} />
                            </div>
                        </div>
                        <p className="flex items-start gap-2 rounded-md bg-surface-raised p-3 text-caption text-ink-muted">
                            <Info
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden
                            />
                            {delivering
                                ? __('The app is sending real email now.')
                                : __(
                                      'No real email is being sent now. Invitations and password resets are not delivered.',
                                  )}
                        </p>
                    </SettingsSection>

                    <SettingsSection
                        title={__('Mail server')}
                        description={__(
                            'The SMTP details from your email provider or cPanel mailbox.',
                        )}
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                id="host"
                                label={__('Host')}
                                error={form.errors.host}
                            >
                                <Input
                                    id="host"
                                    value={form.data.host}
                                    onChange={(event) =>
                                        form.setData('host', event.target.value)
                                    }
                                    placeholder="mail.example.com"
                                    autoComplete="off"
                                />
                            </Field>
                            <Field
                                id="port"
                                label={__('Port')}
                                error={form.errors.port}
                            >
                                <Input
                                    id="port"
                                    type="number"
                                    min={1}
                                    max={65535}
                                    value={form.data.port}
                                    onChange={(event) =>
                                        form.setData('port', event.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="encryption"
                                label={__('Encryption')}
                                error={form.errors.encryption}
                            >
                                <Combobox
                                    id="encryption"
                                    value={form.data.encryption}
                                    onValueChange={(value) =>
                                        form.setData('encryption', value)
                                    }
                                    options={encryptions}
                                    searchPlaceholder={__('Search')}
                                    emptyText={__('Nothing found.')}
                                />
                            </Field>
                            <Field
                                id="username"
                                label={__('Username')}
                                error={form.errors.username}
                            >
                                <Input
                                    id="username"
                                    value={form.data.username}
                                    onChange={(event) =>
                                        form.setData(
                                            'username',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="off"
                                />
                            </Field>
                            <div className="md:col-span-2">
                                <Field
                                    id="password"
                                    label={__('Password')}
                                    help={
                                        settings.has_password
                                            ? __(
                                                  'A password is saved. Leave this blank to keep it.',
                                              )
                                            : undefined
                                    }
                                    error={form.errors.password}
                                >
                                    <PasswordInput
                                        id="password"
                                        value={form.data.password}
                                        onChange={(event) =>
                                            form.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                    />
                                </Field>
                            </div>
                        </div>
                    </SettingsSection>

                    <SettingsSection
                        title={__('Sender')}
                        description={__(
                            'Who the email appears to come from. Use an address on your own domain so it does not land in spam.',
                        )}
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field
                                id="from_address"
                                label={__('From address')}
                                error={form.errors.from_address}
                            >
                                <Input
                                    id="from_address"
                                    type="email"
                                    value={form.data.from_address}
                                    onChange={(event) =>
                                        form.setData(
                                            'from_address',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="no-reply@example.com"
                                    autoComplete="off"
                                />
                            </Field>
                            <Field
                                id="from_name"
                                label={__('From name')}
                                error={form.errors.from_name}
                            >
                                <Input
                                    id="from_name"
                                    value={form.data.from_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'from_name',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={150}
                                    autoComplete="off"
                                />
                            </Field>
                        </div>
                    </SettingsSection>

                    <SettingsSection
                        title={__('Test')}
                        description={__(
                            'Save your changes first, then send yourself a real email to check they work.',
                        )}
                    >
                        <div className="flex flex-wrap items-center gap-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={sendTest}
                                disabled={form.processing || form.isDirty}
                            >
                                {__('Send test email')}
                            </Button>
                            <p className="text-caption text-ink-muted">
                                {__('Sent to :email', { email: testRecipient })}
                            </p>
                        </div>
                    </SettingsSection>
                </form>
            </FormTemplate>
        </>
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
            {help && <p className="text-caption text-ink-muted">{help}</p>}
            <InputError message={error} />
        </div>
    );
}
