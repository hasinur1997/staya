import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { create, edit, index } from '@/routes/users';
import type { UserRow } from '@/types';

type Props = {
    users: UserRow[];
    can: { create: boolean };
};

function UserStatus({ active }: { active: boolean }) {
    const __ = useTranslate();

    return active ? (
        <Badge variant="success">{__('Active')}</Badge>
    ) : (
        <Badge variant="neutral">{__('Deactivated')}</Badge>
    );
}

function UserName({ user }: { user: UserRow }) {
    return user.can.update ? (
        <Link href={edit(user.id)} className="text-ink hover:text-primary">
            {user.name}
        </Link>
    ) : (
        <>{user.name}</>
    );
}

export default function UsersIndex({ users, can }: Props) {
    const __ = useTranslate();
    const lastSeen = (user: UserRow) =>
        user.last_login_at
            ? formatDateTime(user.last_login_at)
            : __('Never signed in');

    const columns: ResponsiveColumn<UserRow>[] = [
        {
            key: 'name',
            header: __('Name'),
            cell: (user) => <UserName user={user} />,
        },
        { key: 'email', header: __('Email'), cell: (user) => user.email },
        {
            key: 'roles',
            header: __('Roles'),
            cell: (user) => user.roles.join(', ') || __('No roles'),
        },
        {
            key: 'status',
            header: __('Status'),
            cell: (user) => <UserStatus active={user.is_active} />,
        },
        {
            key: 'last_login_at',
            header: __('Last sign-in'),
            cell: lastSeen,
            priority: 'low',
        },
    ];

    return (
        <>
            <Head title={__('Users')} />
            <ListTemplate
                header={{
                    title: __('Users'),
                    description: __(
                        'Accounts that can sign in, and the roles that decide what they can do.',
                    ),
                    primaryAction: can.create
                        ? { label: __('New user'), href: create(), icon: Plus }
                        : undefined,
                }}
                resultCount={__(':count users', { count: users.length })}
            >
                <ResponsiveTable
                    caption={__('Users')}
                    columns={columns}
                    rows={users}
                    rowKey={(user) => user.id}
                    card={(user) => ({
                        title: <UserName user={user} />,
                        status: <UserStatus active={user.is_active} />,
                        meta: [
                            user.roles.join(', ') || __('No roles'),
                            lastSeen(user),
                        ],
                    })}
                />
            </ListTemplate>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
