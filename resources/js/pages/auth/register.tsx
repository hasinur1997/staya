import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { MemberFields } from '@/components/member-fields';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslate } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    const __ = useTranslate();
    const { mess } = usePage().props;

    return (
        <>
            <Head title={__('Sign up')} />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <MemberFields
                            errors={errors}
                            maxUploadKb={mess.maxUploadKb}
                        />

                        <fieldset className="flex flex-col gap-4">
                            <legend className="mb-4 text-subhead">
                                {__('Account')}
                            </legend>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="email">
                                    {__('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>
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
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>
                            </div>
                        </fieldset>

                        <Button
                            type="submit"
                            className="w-full"
                            data-test="register-user-button"
                        >
                            {processing && <Spinner />}
                            {__('Submit registration')}
                        </Button>

                        <div className="text-center text-small text-ink-muted">
                            {__('Already have an account?')}{' '}
                            <TextLink href={login()}>{__('Log in')}</TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Join the mess',
    description:
        'Fill in your details. An administrator reviews every registration before you can use the app.',
    wide: true,
};
