import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { DetailRow } from '@/components/detail-row';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DetailTemplate } from '@/components/templates/detail-template';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { index, show } from '@/routes/audit';
import type { AuditDetail } from '@/types';

function Value({ value }: { value: string | null }) {
    return value === null ? (
        <span className="text-ink-muted">—</span>
    ) : (
        <span className="font-mono text-small break-words">{value}</span>
    );
}

export default function AuditShow({ activity }: { activity: AuditDetail }) {
    const __ = useTranslate();

    setLayoutProps({
        breadcrumbs: [
            { title: __('Audit log'), href: index() },
            { title: `#${activity.id}`, href: show(activity.id) },
        ],
    });

    const properties = Object.entries(activity.properties);

    return (
        <>
            <Head title={activity.action_label} />
            <DetailTemplate
                header={{
                    overline: __('Audit entry #:id', { id: activity.id }),
                    title: activity.action_label,
                    status: activity.self_approved ? (
                        <Badge variant="warning">{__('Self-approved')}</Badge>
                    ) : undefined,
                    description: activity.created_at
                        ? formatDateTime(activity.created_at)
                        : undefined,
                }}
            >
                <Card>
                    <CardHeader>
                        <CardTitle className="text-subhead">
                            {__('Details')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="flex flex-col gap-4">
                            <DetailRow label={__('Who')}>
                                {activity.actor_name ??
                                    (activity.email_tried
                                        ? __('Not signed in (:email)', {
                                              email: activity.email_tried,
                                          })
                                        : __('System'))}
                            </DetailRow>
                            <DetailRow label={__('IP address')}>
                                <span className="tabular-nums">
                                    {activity.ip_address ?? '—'}
                                </span>
                            </DetailRow>
                            <DetailRow label={__('Log')}>
                                {activity.log_name}
                            </DetailRow>
                            <DetailRow label={__('Record')}>
                                {activity.subject_type_label ? (
                                    activity.subject_url ? (
                                        <Link
                                            href={activity.subject_url}
                                            className="text-primary hover:underline"
                                        >
                                            {activity.subject_type_label} #
                                            {activity.subject_id}
                                        </Link>
                                    ) : (
                                        `${activity.subject_type_label} #${activity.subject_id ?? ''}`
                                    )
                                ) : (
                                    '—'
                                )}
                            </DetailRow>
                            {properties.map(([key, value]) => (
                                <DetailRow key={key} label={key}>
                                    <Value
                                        value={
                                            typeof value === 'string'
                                                ? value
                                                : JSON.stringify(value)
                                        }
                                    />
                                </DetailRow>
                            ))}
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-subhead">
                            {__('Before and after')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {activity.diff.length === 0 ? (
                            <p className="text-small text-ink-muted">
                                {__('No values were recorded for this action.')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left">
                                    <caption className="sr-only">
                                        {__(
                                            'Values before and after the change',
                                        )}
                                    </caption>
                                    <thead>
                                        <tr className="border-b text-small text-ink-muted">
                                            <th className="py-2 pr-4 font-normal">
                                                {__('Field')}
                                            </th>
                                            <th className="py-2 pr-4 font-normal">
                                                {__('Before')}
                                            </th>
                                            <th className="py-2 font-normal">
                                                {__('After')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {activity.diff.map((row) => (
                                            <tr
                                                key={row.field}
                                                className="border-b align-top last:border-0"
                                            >
                                                <th
                                                    scope="row"
                                                    className="py-2 pr-4 text-small font-normal"
                                                >
                                                    {row.field}
                                                </th>
                                                <td className="py-2 pr-4">
                                                    <Value value={row.old} />
                                                </td>
                                                <td className="py-2">
                                                    <Value value={row.new} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </DetailTemplate>
        </>
    );
}
