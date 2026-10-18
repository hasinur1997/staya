import { Form, Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/lib/i18n';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

type Props = {
    status: 'pending' | 'rejected';
    reason: string | null;
    emailVerified: boolean;
};

export default function RegistrationStatus({
    status,
    reason,
    emailVerified,
}: Props) {
    const __ = useTranslate();

    return (
        <>
            <Head title={__('Registration status')} />
            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-heading">
                        {status === 'pending'
                            ? __('Your registration is being reviewed')
                            : __('Your registration was not approved')}
                    </h1>
                    <div>
                        {status === 'pending' ? (
                            <Badge variant="warning">{__('Pending')}</Badge>
                        ) : (
                            <Badge variant="error">{__('Rejected')}</Badge>
                        )}
                    </div>
                </div>

                {status === 'pending' ? (
                    <p className="text-ink-muted">
                        {__(
                            'An administrator will check your details. You can use the app once they approve your registration.',
                        )}
                    </p>
                ) : (
                    <div className="flex flex-col gap-2">
                        <p className="text-small text-ink-muted">
                            {__('Reason given')}
                        </p>
                        <p className="text-ink">{reason}</p>
                        <p className="text-small text-ink-muted">
                            {__(
                                'Contact the mess manager if you think this is a mistake.',
                            )}
                        </p>
                    </div>
                )}

                {!emailVerified && status === 'pending' && (
                    <Form {...send.form()} className="flex flex-col gap-2">
                        {({ processing, wasSuccessful }) => (
                            <>
                                <p className="text-small text-ink-muted">
                                    {__(
                                        'Please also verify your email address using the link we sent you.',
                                    )}
                                </p>
                                <Button variant="outline" disabled={processing}>
                                    {wasSuccessful
                                        ? __('Verification link sent')
                                        : __('Resend verification email')}
                                </Button>
                            </>
                        )}
                    </Form>
                )}

                <Form {...logout.form()}>
                    <Button variant="ghost" className="w-full">
                        {__('Log out')}
                    </Button>
                </Form>
            </div>
        </>
    );
}
