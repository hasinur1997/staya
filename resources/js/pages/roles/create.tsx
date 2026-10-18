import { Head } from '@inertiajs/react';
import { RoleForm } from '@/components/role-form';
import { useTranslate } from '@/lib/i18n';
import { create, index, store } from '@/routes/roles';
import type { PermissionGroup } from '@/types';

type Props = {
    groups: PermissionGroup[];
};

export default function RolesCreate({ groups }: Props) {
    const __ = useTranslate();

    return (
        <>
            <Head title={__('New role')} />
            <RoleForm
                header={{
                    title: __('New role'),
                    description: __(
                        'You can only include permissions you hold yourself.',
                    ),
                }}
                action={store()}
                cancelHref={index()}
                submitLabel={__('Create role')}
                groups={groups}
                initial={{ name: '', permissions: [] }}
            />
        </>
    );
}

RolesCreate.layout = {
    breadcrumbs: [
        { title: 'Roles', href: index() },
        { title: 'New role', href: create() },
    ],
};
