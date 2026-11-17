import { Head, Link, router } from '@inertiajs/react';
import { AuditSubjectFilter } from '@/components/audit-subject-filter';
import { Pager } from '@/components/pager';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { Badge } from '@/components/ui/badge';
import { Combobox } from '@/components/ui/combobox';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { index, show } from '@/routes/audit';
import type { AuditFilters, AuditRow, Paginated } from '@/types';

type Option = { value: string; label: string };

type Props = {
    activities: Paginated<AuditRow>;
    filters: AuditFilters;
    actors: { id: number; name: string }[];
    logs: Option[];
    actions: Option[];
    subjects: Option[];
};

const ALL = 'all';

export default function AuditIndex({
    activities,
    filters,
    actors,
    logs,
    actions,
    subjects,
}: Props) {
    const __ = useTranslate();

    const apply = (changes: Partial<AuditFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    from: next.from,
                    to: next.to,
                    actor: next.actor ?? undefined,
                    log: next.log ?? undefined,
                    action: next.action ?? undefined,
                    subject: next.subject ?? undefined,
                    subject_id: next.subject
                        ? (next.subject_id ?? undefined)
                        : undefined,
                },
            }),
            {},
            { preserveScroll: true },
        );
    };

    const withAll = (label: string, options: Option[]) => [
        { value: ALL, label },
        ...options,
    ];

    const when = (row: AuditRow) => (
        <Link href={show(row.id)} className="text-ink hover:text-primary">
            {row.created_at ? formatDateTime(row.created_at) : ''}
        </Link>
    );
    const what = (row: AuditRow) => (
        <span className="flex flex-wrap items-center gap-2">
            {row.action_label}
            {row.self_approved && (
                <Badge variant="warning">{__('Self-approved')}</Badge>
            )}
        </span>
    );
    const who = (row: AuditRow) =>
        row.actor_name ??
        (row.email_tried
            ? __('Not signed in (:email)', { email: row.email_tried })
            : __('System'));
    const subject = (row: AuditRow) =>
        row.subject_type_label
            ? `${row.subject_type_label} #${row.subject_id ?? ''}`
            : '';

    const columns: ResponsiveColumn<AuditRow>[] = [
        { key: 'when', header: __('When'), cell: when },
        { key: 'action', header: __('Action'), cell: what },
        { key: 'actor', header: __('Who'), cell: who },
        {
            key: 'subject',
            header: __('Record'),
            cell: subject,
            priority: 'low',
        },
        {
            key: 'ip',
            header: __('IP address'),
            cell: (row) => (
                <span className="tabular-nums">{row.ip_address ?? ''}</span>
            ),
            priority: 'low',
        },
    ];

    const activeFilterCount =
        (filters.log ? 1 : 0) +
        (filters.action ? 1 : 0) +
        (filters.subject ? 1 : 0);

    return (
        <>
            <Head title={__('Audit log')} />
            <ListTemplate
                header={{
                    title: __('Audit log'),
                    description: __(
                        'A read-only history of every financial and access change, shown in Asia/Dhaka time. Entries cannot be edited or deleted.',
                    ),
                }}
                filters={
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="audit-from">{__('From')}</Label>
                            <DatePicker
                                id="audit-from"
                                value={filters.from}
                                max={filters.to}
                                onValueChange={(from) =>
                                    from && apply({ from })
                                }
                                className="w-48"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="audit-to">{__('To')}</Label>
                            <DatePicker
                                id="audit-to"
                                value={filters.to}
                                min={filters.from}
                                onValueChange={(to) => to && apply({ to })}
                                className="w-48"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="audit-actor">{__('Who')}</Label>
                            <Combobox
                                id="audit-actor"
                                value={
                                    filters.actor ? String(filters.actor) : ALL
                                }
                                onValueChange={(next) =>
                                    apply({
                                        actor:
                                            next === ALL ? null : Number(next),
                                    })
                                }
                                options={withAll(
                                    __('Everyone'),
                                    actors.map((actor) => ({
                                        value: String(actor.id),
                                        label: actor.name,
                                    })),
                                )}
                                searchPlaceholder={__('Search')}
                                emptyText={__('Nothing found.')}
                                className="w-56"
                            />
                        </div>
                    </div>
                }
                moreFilters={
                    <>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="audit-log">{__('Log')}</Label>
                            <Combobox
                                id="audit-log"
                                value={filters.log ?? ALL}
                                onValueChange={(next) =>
                                    apply({ log: next === ALL ? null : next })
                                }
                                options={withAll(__('All logs'), logs)}
                                searchPlaceholder={__('Search')}
                                emptyText={__('Nothing found.')}
                                className="md:w-52"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="audit-action">{__('Action')}</Label>
                            <Combobox
                                id="audit-action"
                                value={filters.action ?? ALL}
                                onValueChange={(next) =>
                                    apply({
                                        action: next === ALL ? null : next,
                                    })
                                }
                                options={withAll(__('All actions'), actions)}
                                searchPlaceholder={__('Search')}
                                emptyText={__('Nothing found.')}
                                className="md:w-60"
                            />
                        </div>
                        <AuditSubjectFilter
                            subjects={subjects}
                            subject={filters.subject}
                            subjectId={filters.subject_id}
                            onChange={(subject, subject_id) =>
                                apply({ subject, subject_id })
                            }
                        />
                    </>
                }
                activeFilterCount={activeFilterCount}
                resultCount={
                    activities.total === 1
                        ? __('1 entry')
                        : __(':count entries', { count: activities.total })
                }
                pagination={<Pager page={activities} />}
            >
                {activities.data.length === 0 ? (
                    <p className="text-ink-muted">
                        {__('No entries match these filters.')}
                    </p>
                ) : (
                    <ResponsiveTable
                        caption={__('Audit log entries')}
                        columns={columns}
                        rows={activities.data}
                        rowKey={(row) => row.id}
                        card={(row) => ({
                            title: when(row),
                            value: what(row),
                            meta: [
                                who(row),
                                subject(row),
                                row.ip_address ?? '',
                            ],
                        })}
                    />
                )}
            </ListTemplate>
        </>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
