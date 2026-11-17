import { router } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { EditableCard } from '@/components/editable-card';
import { ImageDropzone } from '@/components/image-dropzone';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { nid as nidDownload } from '@/routes/members';
import { update as updateSection } from '@/routes/members/sections';
import type { MemberProfile } from '@/types';

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-1 md:grid md:grid-cols-3 md:gap-4">
            <dt className="text-small text-ink-muted">{label}</dt>
            <dd className="text-ink md:col-span-2">{children || '—'}</dd>
        </div>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

type CardProps = { member: MemberProfile; canEdit: boolean };

export function PersonalDetailsCard({
    member,
    canEdit,
    canChangeEmail,
}: CardProps & { canChangeEmail: boolean }) {
    const __ = useTranslate();

    return (
        <EditableCard
            title={__('Personal details')}
            canEdit={canEdit}
            form={updateSection.form({
                member: member.id,
                section: 'personal',
            })}
            fields={(errors) => (
                <>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            id="first_name"
                            label={__('First name')}
                            error={errors.first_name}
                        >
                            <Input
                                id="first_name"
                                name="first_name"
                                defaultValue={member.first_name}
                                required
                            />
                        </Field>
                        <Field
                            id="last_name"
                            label={__('Last name')}
                            error={errors.last_name}
                        >
                            <Input
                                id="last_name"
                                name="last_name"
                                defaultValue={member.last_name}
                                required
                            />
                        </Field>
                    </div>
                    <Field
                        id="phone"
                        label={__('Mobile number')}
                        error={errors.phone}
                    >
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            defaultValue={member.phone}
                            required
                        />
                    </Field>
                    <Field
                        id="email"
                        label={__('Email address')}
                        error={errors.email}
                    >
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            defaultValue={member.email}
                            readOnly={!canChangeEmail}
                            required
                        />
                        {!canChangeEmail && (
                            <p className="text-small text-ink-muted">
                                {__(
                                    'Only someone allowed to edit this account can change its email.',
                                )}
                            </p>
                        )}
                    </Field>
                </>
            )}
        >
            <dl className="flex flex-col gap-4">
                <Row label={__('Name')}>{member.name}</Row>
                <Row label={__('Mobile number')}>{member.phone}</Row>
                <Row label={__('Email')}>{member.email}</Row>
            </dl>
        </EditableCard>
    );
}

export function FamilyDetailsCard({ member, canEdit }: CardProps) {
    const __ = useTranslate();

    return (
        <EditableCard
            title={__('Family and address')}
            canEdit={canEdit}
            form={updateSection.form({ member: member.id, section: 'family' })}
            fields={(errors) => (
                <>
                    <Field
                        id="father_name"
                        label={__("Father's name")}
                        error={errors.father_name}
                    >
                        <Input
                            id="father_name"
                            name="father_name"
                            defaultValue={member.father_name}
                            required
                        />
                    </Field>
                    <Field
                        id="mother_name"
                        label={__("Mother's name")}
                        error={errors.mother_name}
                    >
                        <Input
                            id="mother_name"
                            name="mother_name"
                            defaultValue={member.mother_name}
                            required
                        />
                    </Field>
                    <Field
                        id="permanent_address"
                        label={__('Permanent address')}
                        error={errors.permanent_address}
                    >
                        <Textarea
                            id="permanent_address"
                            name="permanent_address"
                            defaultValue={member.permanent_address}
                            rows={3}
                            required
                        />
                    </Field>
                </>
            )}
        >
            <dl className="flex flex-col gap-4">
                <Row label={__("Father's name")}>{member.father_name}</Row>
                <Row label={__("Mother's name")}>{member.mother_name}</Row>
                <Row label={__('Permanent address')}>
                    <span className="whitespace-pre-line">
                        {member.permanent_address}
                    </span>
                </Row>
            </dl>
        </EditableCard>
    );
}

/**
 * The NID number stays hidden until asked for (each reveal is audit-logged).
 * Editing starts only once it is showing, so it is never changed blind.
 */
export function NidDetailsCard({
    member,
    nid,
    canEdit,
}: CardProps & { nid?: string }) {
    const __ = useTranslate();
    const [loading, setLoading] = useState(false);

    const reveal = () =>
        router.reload({
            only: ['nid'],
            onStart: () => setLoading(true),
            onFinish: () => setLoading(false),
        });

    return (
        <EditableCard
            title={__('National ID')}
            canEdit={canEdit && nid !== undefined}
            form={updateSection.form({ member: member.id, section: 'nid' })}
            fields={(errors) => (
                <>
                    <Field
                        id="nid_number"
                        label={__('NID number')}
                        error={errors.nid_number}
                    >
                        <Input
                            id="nid_number"
                            name="nid_number"
                            inputMode="numeric"
                            defaultValue={nid}
                            required
                        />
                    </Field>
                    <Field id="nid_file" label={__('New NID copy')}>
                        <ImageDropzone
                            name="nid_file"
                            id="nid_file"
                            allowPdf
                            maxWidth={2000}
                            subject={__('NID copy')}
                            error={errors.nid_file}
                        />
                        <p className="text-small text-ink-muted">
                            {__('Leave empty to keep the current copy.')}
                        </p>
                    </Field>
                </>
            )}
            footer={
                <p className="text-small text-ink-muted">
                    {__(
                        'Viewing or downloading NID data is recorded in the audit log.',
                    )}
                </p>
            }
        >
            <dl className="flex flex-col gap-4">
                <Row label={__('NID number')}>
                    {nid ? (
                        <span className="font-mono tabular-nums">{nid}</span>
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
        </EditableCard>
    );
}

/** When the member registered and who reviewed them. */
export function RegistrationCard({ member }: { member: MemberProfile }) {
    const __ = useTranslate();

    return (
        <Card className="h-full">
            <CardHeader>
                <CardTitle className="text-subhead">
                    {__('Registration')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <dl className="flex flex-col gap-4">
                    <Row label={__('Status')}>{member.status_label}</Row>
                    <Row label={__('Registered')}>
                        {member.registered_at
                            ? formatDateTime(member.registered_at)
                            : ''}
                    </Row>
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
