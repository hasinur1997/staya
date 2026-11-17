import { Form, Head, setLayoutProps, usePage } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import InputError from '@/components/input-error';
import { MemberFields } from '@/components/member-fields';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { login } from '@/routes';
import { accept } from '@/routes/invitations';

type Props = {
    token: string;
    state: 'pending' | 'used' | 'revoked' | 'expired' | 'invalid';
    /** Only sent while the invitation can still be used. */
    email: string | null;
    expiresAt: string | null;
    passwordRules: string;
};

function Unavailable({ title, message }: { title: string; message: string }) {
    const __ = useTranslate();

    return (
        <div
            role="status"
            className="flex flex-col items-center gap-4 py-6 text-center"
        >
            <CircleAlert className="size-8 text-ink-muted" aria-hidden />
            <h2 className="text-title">{title}</h2>
            <p className="text-ink-muted">{message}</p>
            <Button asChild variant="outline">
                <a href={login.url()}>{__('Go to log in')}</a>
            </Button>
        </div>
    );
}

export default function Invitation({
    token,
    state,
    email,
    expiresAt,
    passwordRules,
}: Props) {
    const __ = useTranslate();
    const { mess } = usePage().props;

    if (state !== 'pending') {
        setLayoutProps({ title: 'Invitation', description: '' });
    }

    if (state === 'used') {
        return (
            <>
                <Head title={__('Invitation already used')} />
                <Unavailable
                    title={__('This invitation link was already used')}
                    message={__(
                        'Someone has already filled in this invitation. If that was you, log in with your email and password.',
                    )}
                />
            </>
        );
    }

    if (state === 'expired') {
        return (
            <>
                <Head title={__('Invitation expired')} />
                <Unavailable
                    title={__('This invitation link has expired')}
                    message={__(
                        'Ask the person who invited you to send a new invitation.',
                    )}
                />
            </>
        );
    }

    if (state === 'revoked' || state === 'invalid') {
        return (
            <>
                <Head title={__('Invitation not valid')} />
                <Unavailable
                    title={__('This invitation link is not valid')}
                    message={
                        state === 'revoked'
                            ? __(
                                  'This invitation was cancelled. Ask the person who invited you to send a new one.',
                              )
                            : __(
                                  'Check that you copied the whole link from the email.',
                              )
                    }
                />
            </>
        );
    }

    return (
        <>
            <Head title={__('Join the mess')} />
            <Form
                {...accept.form(token)}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <fieldset className="flex flex-col gap-4">
                            <legend className="mb-4 text-title">
                                {__('Account')}
                            </legend>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="email">
                                    {__('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={email ?? ''}
                                    readOnly
                                    aria-describedby="email-help"
                                />
                                <p
                                    id="email-help"
                                    className="text-caption text-ink-muted"
                                >
                                    {expiresAt
                                        ? __(
                                              'This is the address you were invited on. The link works once and expires on :date.',
                                              {
                                                  date: formatDateTime(
                                                      expiresAt,
                                                  ),
                                              },
                                          )
                                        : ''}
                                </p>
                                <InputError message={errors.email} />
                            </div>
                        </fieldset>

                        <MemberFields
                            errors={errors}
                            maxUploadKb={mess.maxUploadKb}
                        />

                        <fieldset className="flex flex-col gap-4">
                            <legend className="mb-4 text-title">
                                {__('Password')}
                            </legend>
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="password">
                                        {__('Password')}
                                    </Label>
                                    <PasswordInput
                                        id="password"
                                        required
                                        autoComplete="new-password"
                                        name="password"
                                        passwordrules={passwordRules}
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="password_confirmation">
                                        {__('Confirm password')}
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        required
                                        autoComplete="new-password"
                                        name="password_confirmation"
                                        passwordrules={passwordRules}
                                    />
                                </div>
                            </div>
                        </fieldset>

                        <Button
                            type="submit"
                            className="w-full"
                            data-test="accept-invitation-button"
                        >
                            {processing && <Spinner />}
                            {__('Create my account')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

Invitation.layout = {
    title: 'Join the mess',
    description:
        'You were invited. Fill in your details to create your account. You can use the app straight away.',
    wide: true,
};
