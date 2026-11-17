import { router } from '@inertiajs/react';
import { DetailRow } from '@/components/detail-row';
import { Download, Eye } from 'lucide-react';
import { useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import { formatBusinessDate, formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { localizedText } from '@/lib/locale';
import { nid as nidDownload, photo } from '@/routes/members';
import type { MemberProfile } from '@/types';

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
                    <AvatarFallback className="text-title">
                        {getInitials(member.name)}
                    </AvatarFallback>
                </Avatar>
                <div className="flex flex-col gap-1">
                    <p className="text-title text-ink capitalize">
                        {member.name}
                    </p>
                    <p className="text-caption text-ink-muted">
                        {member.email}
                    </p>
                    <p className="text-caption text-ink-muted">
                        {member.phone}
                    </p>
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
                <CardTitle className="text-title">{__('Details')}</CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="flex flex-col gap-4">
                    <DetailRow label={__('Mobile number')}>
                        {member.phone}
                    </DetailRow>
                    <DetailRow label={__('Email')}>{member.email}</DetailRow>
                    <DetailRow label={__("Father's name")}>
                        {localizedText(
                            member.father_name,
                            member.father_name_bn,
                        )}
                    </DetailRow>
                    <DetailRow label={__("Mother's name")}>
                        {localizedText(
                            member.mother_name,
                            member.mother_name_bn,
                        )}
                    </DetailRow>
                    <DetailRow label={__('Permanent address')}>
                        <span className="whitespace-pre-line">
                            {member.permanent_address}
                        </span>
                    </DetailRow>
                    {member.registered_at && (
                        <DetailRow label={__('Registered')}>
                            {formatDateTime(member.registered_at)}
                        </DetailRow>
                    )}
                    {member.reviewed_at && member.reviewed_by && (
                        <DetailRow label={__('Reviewed')}>
                            {__(':time by :name', {
                                time: formatDateTime(member.reviewed_at),
                                name: member.reviewed_by,
                            })}
                        </DetailRow>
                    )}
                    {member.rejection_reason && (
                        <DetailRow label={__('Rejection reason')}>
                            {member.rejection_reason}
                        </DetailRow>
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
                <CardTitle className="text-title">
                    {__('National ID')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="flex flex-col gap-4">
                    <DetailRow label={__('NID number')}>
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
                    </DetailRow>
                    <DetailRow label={__('NID copy')}>
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
                    </DetailRow>
                </dl>
                <p className="mt-4 text-caption text-ink-muted">
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
                <CardTitle className="text-title">{__('Stays')}</CardTitle>
            </CardHeader>
            <CardContent>
                {member.stays.length === 0 ? (
                    <p className="text-caption text-ink-muted">
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
                                <span className="text-caption text-ink-muted">
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
