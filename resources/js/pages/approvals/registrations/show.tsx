import { Head, setLayoutProps } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/form-dialog';
import {
    MemberDetailsCard,
    MemberNidCard,
    MemberSummaryCard,
} from '@/components/member-details';
import { MemberStatusBadge } from '@/components/member-status-badge';
import { DetailTemplate } from '@/components/templates/detail-template';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { approve, index, reject, show } from '@/routes/approvals/registrations';
import { index as allMembers } from '@/routes/members';
import type { MemberProfile } from '@/types';

type Props = {
    member: MemberProfile;
    nid?: string;
    /** Today in Asia/Dhaka (Y-m-d). */
    today: string;
    can: { review: boolean; viewNid: boolean };
};

export default function RegistrationShow({ member, nid, today, can }: Props) {
    const __ = useTranslate();
    const [dialog, setDialog] = useState<'approve' | 'reject' | null>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: __('Members'), href: allMembers() },
            { title: __('Registrations'), href: index() },
            { title: member.name, href: show(member.id) },
        ],
    });

    const closeDialog = (open: boolean) => !open && setDialog(null);

    return (
        <>
            <Head title={__('Registration: :name', { name: member.name })} />
            <DetailTemplate
                header={{
                    overline: __('Registration'),
                    title: member.name,
                    status: (
                        <MemberStatusBadge
                            status={member.status}
                            label={member.status_label}
                        />
                    ),
                    description: member.registered_at
                        ? __('Signed up :time', {
                              time: formatDateTime(member.registered_at),
                          })
                        : undefined,
                    primaryAction: can.review
                        ? {
                              label: __('Approve'),
                              icon: Check,
                              onClick: () => setDialog('approve'),
                          }
                        : undefined,
                    secondaryActions: can.review
                        ? [
                              {
                                  label: __('Reject'),
                                  icon: X,
                                  onClick: () => setDialog('reject'),
                              },
                          ]
                        : [],
                }}
                aside={<MemberSummaryCard member={member} />}
            >
                <MemberDetailsCard member={member} />
                {can.viewNid && <MemberNidCard member={member} nid={nid} />}
            </DetailTemplate>

            <FormDialog
                open={dialog === 'approve'}
                onOpenChange={closeDialog}
                title={__('Approve :name?', { name: member.name })}
                description={__(
                    'They get the Member role and their first stay starts on the join date.',
                )}
                action={approve(member.id)}
                field={{
                    type: 'date',
                    name: 'joined_on',
                    label: __('Joined on'),
                    defaultValue: today,
                    max: today,
                }}
                submitLabel={__('Approve')}
            />

            <FormDialog
                open={dialog === 'reject'}
                onOpenChange={closeDialog}
                title={__('Reject :name?', { name: member.name })}
                description={__(
                    'The applicant sees this reason when they sign in. They still have no access.',
                )}
                action={reject(member.id)}
                field={{ type: 'reason', name: 'reason', label: __('Reason') }}
                submitLabel={__('Reject registration')}
                destructive
            />
        </>
    );
}
