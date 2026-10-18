import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { Badge } from '@/components/ui/badge';
import { useTranslate } from '@/lib/i18n';
import { create, edit, index } from '@/routes/roles';
import type { RoleRow } from '@/types';

type Props = {
    roles: RoleRow[];
    can: { create: boolean };
};

function RoleName({ role }: { role: RoleRow }) {
    return role.can.update ? (
        <Link href={edit(role.id)} className="text-ink hover:text-primary">
            {role.name}
        </Link>
    ) : (
        <>{role.name}</>
    );
}

export default function RolesIndex({ roles, can }: Props) {
    const __ = useTranslate();
    const kind = (role: RoleRow) =>
        role.is_system ? (
            <Badge variant="neutral">{__('System')}</Badge>
        ) : (
            <Badge variant="outline">{__('Custom')}</Badge>
        );

    const columns: ResponsiveColumn<RoleRow>[] = [
        {
            key: 'name',
            header: __('Name'),
            cell: (role) => <RoleName role={role} />,
        },
        { key: 'kind', header: __('Type'), cell: kind },
        {
            key: 'users',
            header: __('Users'),
            cell: (role) => role.users_count,
            align: 'right',
        },
        {
            key: 'permissions',
            header: __('Permissions'),
            cell: (role) => role.permissions_count,
            align: 'right',
        },
    ];

    return (
        <>
            <Head title={__('Roles')} />
            <ListTemplate
                header={{
                    title: __('Roles'),
                    description: __(
                        'Named sets of permissions. A user can do whatever any of their roles allows.',
                    ),
                    primaryAction: can.create
                        ? { label: __('New role'), href: create(), icon: Plus }
                        : undefined,
                }}
                resultCount={__(':count roles', { count: roles.length })}
            >
                <ResponsiveTable
                    caption={__('Roles')}
                    columns={columns}
                    rows={roles}
                    rowKey={(role) => role.id}
                    card={(role) => ({
                        title: <RoleName role={role} />,
                        status: kind(role),
                        meta: [
                            __(':count users', { count: role.users_count }),
                            __(':count permissions', {
                                count: role.permissions_count,
                            }),
                        ],
                    })}
                />
            </ListTemplate>
        </>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
