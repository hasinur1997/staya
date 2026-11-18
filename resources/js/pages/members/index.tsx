import { MemberAvatar } from '@/components/member-avatar';
import { Combobox } from '@/components/ui/combobox';
import { MemberName } from '@/components/member-name';
import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { MemberStatusBadge } from '@/components/member-status-badge';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { MemberTabs } from '@/components/review-tabs';
import { ListTemplate } from '@/components/templates/list-template';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { index as invitations } from '@/routes/invitations';
import { create, index } from '@/routes/members';
import type {
    MemberFilters,
    MemberRow,
    MemberStatusValue,
    Option,
} from '@/types';

type Props = {
    members: MemberRow[];
    filters: MemberFilters;
    statuses: Option<MemberStatusValue>[];
    can: { create: boolean };
};

const ALL = 'all';

export default function MembersIndex({
    members,
    filters,
    statuses,
    can,
}: Props) {
    const __ = useTranslate();
    const [search, setSearch] = useState(filters.search);

    const applyFilters = (changes: Partial<MemberFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    status: next.status ?? undefined,
                    search: next.search || undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        applyFilters({ search });
    };

    const stay = (member: MemberRow) => {
        if (!member.joined_on) {
            return __('No stays yet');
        }

        return member.left_on
            ? __('Left :date', { date: formatBusinessDate(member.left_on) })
            : __('Since :date', { date: formatBusinessDate(member.joined_on) });
    };

    const name = (member: MemberRow) => (
        <span className="flex items-center gap-3">
            <MemberAvatar
                id={member.id}
                name={member.name}
                hasPhoto={member.has_photo}
                version={member.media_version}
            />
            <MemberName
                id={member.id}
                name={member.name}
                linked
                className="font-semibold"
            />
        </span>
    );

    const status = (member: MemberRow) => (
        <MemberStatusBadge status={member.status} label={member.status_label} />
    );

    const columns: ResponsiveColumn<MemberRow>[] = [
        { key: 'name', header: __('Name'), cell: name },
        { key: 'phone', header: __('Mobile'), cell: (member) => member.phone },
        {
            key: 'email',
            header: __('Email'),
            cell: (member) => member.email,
            priority: 'low',
        },
        { key: 'stay', header: __('Stay'), cell: stay },
        { key: 'status', header: __('Status'), cell: status },
    ];

    return (
        <>
            <Head title={__('Members')} />
            <ListTemplate
                header={{
                    title: __('Members'),
                    description: __(
                        'Everyone who lives or has lived in the mess, with their stays.',
                    ),
                    children: <MemberTabs current="members" />,
                    secondaryActions: can.create
                        ? [{ label: __('Invitations'), href: invitations() }]
                        : [],
                    primaryAction: can.create
                        ? {
                              label: __('New member'),
                              href: create(),
                              icon: Plus,
                          }
                        : undefined,
                }}
                filters={
                    <form
                        noValidate
                        onSubmit={submitSearch}
                        className="flex flex-col gap-1"
                        role="search"
                    >
                        <Label htmlFor="member-search">{__('Search')}</Label>
                        <Input
                            id="member-search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onBlur={() =>
                                search !== filters.search &&
                                applyFilters({ search })
                            }
                            placeholder={__('Name or mobile number')}
                            className="w-64"
                        />
                    </form>
                }
                moreFilters={
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="member-status">{__('Status')}</Label>
                        <Combobox
                            id="member-status"
                            value={filters.status ?? ALL}
                            onValueChange={(next) =>
                                applyFilters({
                                    status:
                                        next === ALL
                                            ? null
                                            : (next as MemberStatusValue),
                                })
                            }
                            options={[
                                { value: ALL, label: __('All statuses') },
                                ...statuses,
                            ]}
                            searchPlaceholder={__('Search')}
                            emptyText={__('Nothing found.')}
                            className="md:w-48"
                        />
                    </div>
                }
                activeFilterCount={filters.status ? 1 : 0}
                resultCount={
                    members.length === 1
                        ? __('1 member')
                        : __(':count members', { count: members.length })
                }
            >
                {members.length === 0 ? (
                    <p className="text-ink-muted">
                        {filters.status || filters.search
                            ? __('No members match these filters.')
                            : __('No members yet.')}
                    </p>
                ) : (
                    <ResponsiveTable
                        caption={__('Members')}
                        columns={columns}
                        rows={members}
                        rowKey={(member) => member.id}
                        card={(member) => ({
                            title: name(member),
                            status: status(member),
                            meta: [member.phone, stay(member)],
                        })}
                    />
                )}
            </ListTemplate>
        </>
    );
}

MembersIndex.layout = {
    breadcrumbs: [{ title: 'Members', href: index() }],
};
