import { Head } from '@inertiajs/react';
import { UserForm } from '@/components/user-form';
import { useTranslate } from '@/lib/i18n';
import { create, index, store } from '@/routes/users';
import type { RoleOption } from '@/types';

type Props = {
    roles: RoleOption[];
    passwordRules: string;
};

export default function UsersCreate({ roles, passwordRules }: Props) {
    const __ = useTranslate();

    return (
        <>
            <Head title={__('New user')} />
            <UserForm
                header={{
                    title: __('New user'),
                    description: __(
                        'They will be asked to verify their email address before signing in.',
                    ),
                }}
                action={store()}
                cancelHref={index()}
                submitLabel={__('Create user')}
                roles={roles}
                initial={{ name: '', name_bn: '', email: '', roles: [] }}
                passwordRules={passwordRules}
            />
        </>
    );
}

UsersCreate.layout = {
    breadcrumbs: [
        { title: 'Users', href: index() },
        { title: 'New user', href: create() },
    ],
};
