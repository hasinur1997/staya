import { useForm } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import type { PageHeaderProps } from '@/components/page-header';
import { RoleChecklist } from '@/components/role-checklist';
import { FormTemplate } from '@/components/templates/form-template';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';
import type { RouteDefinition } from '@/wayfinder';
import type { RoleOption } from '@/types';

type Props = {
    header: Omit<PageHeaderProps, 'primaryAction' | 'mobileAction'>;
    action: RouteDefinition<'post' | 'put'>;
    cancelHref: NonNullable<InertiaLinkProps['href']>;
    submitLabel: string;
    roles: RoleOption[];
    initial: { name: string; email: string; roles: number[] };
    /** New accounts set a password; existing ones reset it by email. */
    passwordRules?: string;
    /** Set when roles can't be changed at all; they are then not sent. */
    rolesLockedReason?: string | null;
};

export function UserForm({
    header,
    action,
    cancelHref,
    submitLabel,
    roles,
    initial,
    passwordRules,
    rolesLockedReason = null,
}: Props) {
    const __ = useTranslate();
    const withPassword = passwordRules !== undefined;
    const form = useForm({
        ...initial,
        password: '',
        password_confirmation: '',
    });

    form.transform(({ password, password_confirmation, roles, ...data }) => ({
        ...data,
        ...(withPassword ? { password, password_confirmation } : {}),
        ...(rolesLockedReason ? {} : { roles }),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(action, { preserveScroll: true });
    };

    return (
        <FormTemplate
            header={header}
            formId="user-form"
            submitLabel={submitLabel}
            processing={form.processing}
            cancelHref={cancelHref}
        >
            <form
                id="user-form"
                onSubmit={submit}
                className="flex flex-col gap-4 md:gap-6"
            >
                <Card>
                    <fieldset className="flex flex-col gap-4 px-4">
                        <legend className="mb-4 text-subhead">
                            {__('Account')}
                        </legend>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="name">{__('Name')}</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={form.errors.name} />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="email">
                                    {__('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={form.errors.email} />
                            </div>
                        </div>
                        {withPassword && (
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="password">
                                        {__('Password')}
                                    </Label>
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
                                        passwordrules={passwordRules}
                                        required
                                    />
                                    <InputError
                                        message={form.errors.password}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="password_confirmation">
                                        {__('Confirm password')}
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        value={form.data.password_confirmation}
                                        onChange={(event) =>
                                            form.setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                        required
                                    />
                                </div>
                            </div>
                        )}
                    </fieldset>
                </Card>

                <RoleChecklist
                    roles={roles}
                    value={form.data.roles}
                    onChange={(value) => form.setData('roles', value)}
                    lockedReason={rolesLockedReason}
                />
                <InputError message={form.errors.roles} />
            </form>
        </FormTemplate>
    );
}
