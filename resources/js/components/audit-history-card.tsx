import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { show } from '@/routes/audit';
import type { AuditRow } from '@/types';

/**
 * Audit history of one record, newest first. Only rendered when the server
 * sent a history, which it does only to users with `audit.view`.
 */
export function AuditHistoryCard({ history }: { history: AuditRow[] }) {
    const __ = useTranslate();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-title">
                    {__('Audit history')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {history.length === 0 ? (
                    <p className="text-caption text-ink-muted">
                        {__('Nothing has been logged for this record.')}
                    </p>
                ) : (
                    <ol className="flex flex-col divide-y divide-border">
                        {history.map((entry) => (
                            <li
                                key={entry.id}
                                className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0"
                            >
                                <span className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={show(entry.id)}
                                        className="text-ink hover:text-primary"
                                    >
                                        {entry.action_label}
                                    </Link>
                                    {entry.self_approved && (
                                        <Badge variant="warning">
                                            {__('Self-approved')}
                                        </Badge>
                                    )}
                                </span>
                                <span className="text-caption text-ink-muted">
                                    {entry.created_at
                                        ? formatDateTime(entry.created_at)
                                        : ''}
                                    {entry.actor_name
                                        ? ` · ${entry.actor_name}`
                                        : ''}
                                    {entry.ip_address
                                        ? ` · ${entry.ip_address}`
                                        : ''}
                                </span>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
