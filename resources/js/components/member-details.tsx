import { router } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import { formatBusinessDate, formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { nid as nidDownload, photo } from '@/routes/members';
import type { MemberProfile } from '@/types';

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-1 md:grid md:grid-cols-3 md:gap-4">
            <dt className="text-small text-ink-muted">{label}</dt>
            <dd className="text-ink md:col-span-2">{children}</dd>
        </div>
    );
}

/** Photo, name and contact details, for the aside of a member page. */
export function MemberSummaryCard({ member }: { member: MemberProfile }) {
    const __ = useTranslate();
    const getInitials = useInitials();

    return (
        <Card>
            <CardContent className="flex flex-col items-center gap-4 text-center">
                <Avatar className="size-24">
                    {member.has_photo && (
                        <AvatarImage
                            src={photo.url(member.id)}
                            alt={__('Photo of :name', { name: member.name })}
                        />
                    )}
                    <AvatarFallback className="text-subhead">
                        {getInitials(member.name)}
                    </AvatarFallback>
                </Avatar>
                <div className="flex flex-col gap-1">
                    <p className="text-subhead text-ink">{member.name}</p>
                    <p className="text-small text-ink-muted">{member.email}</p>
                    <p className="text-small text-ink-muted">{member.phone}</p>
                </div>
            </CardContent>
        </Card>
    );
}

/** Personal and family details. Never shows NID data. */
export function MemberDetailsCard({ member }: { member: MemberProfile }) {
    const __ = useTranslate();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-subhead">{__('Details')}</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="flex flex-col gap-4">
                    <Row label={__('Mobile number')}>{member.phone}</Row>
                    <Row label={__('Email')}>{member.email}</Row>
                    <Row label={__("Father's name")}>{member.father_name}</Row>
                    <Row label={__("Mother's name")}>{member.mother_name}</Row>
                    <Row label={__('Permanent address')}>
                        <span className="whitespace-pre-line">
                            {member.permanent_address}
                        </span>
                    </Row>
                    {member.registered_at && (
                        <Row label={__('Registered')}>
                            {formatDateTime(member.registered_at)}
                        </Row>
                    )}
                    {member.reviewed_at && member.reviewed_by && (
                        <Row label={__('Reviewed')}>
                            {__(':time by :name', {
                                time: formatDateTime(member.reviewed_at),
                                name: member.reviewed_by,
                            })}
                        </Row>
                    )}
                    {member.rejection_reason && (
                        <Row label={__('Rejection reason')}>
                            {member.rejection_reason}
                        </Row>
                    )}
                </dl>
            </CardContent>
        </Card>
    );
}

/**
 * The NID number stays hidden until asked for; each reveal is loaded from
 * the server, which records it in the audit log.
 */
export function MemberNidCard({
    member,
    nid,
}: {
    member: MemberProfile;
    nid?: string;
}) {
    const __ = useTranslate();
    const [loading, setLoading] = useState(false);

    const reveal = () =>
        router.reload({
            only: ['nid'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-subhead">
                    {__('National ID')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="flex flex-col gap-4">
                    <Row label={__('NID number')}>
                        {nid ? (
                            <span className="font-mono tabular-nums">
                                {nid}
                            </span>
                        ) : (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={reveal}
                                disabled={loading}
                            >
                                <Eye aria-hidden />
                                {__('Show NID number')}
                            </Button>
                        )}
                    </Row>
                    <Row label={__('NID copy')}>
                        {member.has_nid_file ? (
                            <Button variant="outline" size="sm" asChild>
                                <a href={nidDownload.url(member.id)}>
                                    <Download aria-hidden />
                                    {__('Download')}
                                </a>
                            </Button>
                        ) : (
                            __('Not on file')
                        )}
                    </Row>
                </dl>
                <p className="mt-4 text-small text-ink-muted">
                    {__(
                        'Viewing or downloading NID data is recorded in the audit log.',
                    )}
                </p>
            </CardContent>
        </Card>
    );
}

/** Every stay, newest first. Past stays are never edited. */
export function MemberStaysCard({ member }: { member: MemberProfile }) {
    const __ = useTranslate();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-subhead">{__('Stays')}</CardTitle>
            </CardHeader>
            <CardContent>
                {member.stays.length === 0 ? (
                    <p className="text-small text-ink-muted">
                        {__('No stays yet. The first one opens on approval.')}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y divide-border">
                        {member.stays.map((stay) => (
                            <li
                                key={stay.id}
                                className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                            >
                                <span className="tabular-nums">
                                    {formatBusinessDate(stay.joined_on)}
                                    {' – '}
                                    {stay.left_on
                                        ? formatBusinessDate(stay.left_on)
                                        : __('present')}
                                </span>
                                <span className="text-small text-ink-muted">
                                    {stay.left_on ? __('Ended') : __('Current')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
