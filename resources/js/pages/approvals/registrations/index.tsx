import { Head, Link } from '@inertiajs/react';
import { MemberTabs } from '@/components/review-tabs';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { ListTemplate } from '@/components/templates/list-template';
import { Badge } from '@/components/ui/badge';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { index, show } from '@/routes/approvals/registrations';
import { index as allMembers } from '@/routes/members';
import type { RegistrationRow } from '@/types';

type Props = {
    registrations: RegistrationRow[];
};

function EmailState({ verified }: { verified: boolean }) {
    const __ = useTranslate();

    return verified ? (
        <Badge variant="success">{__('Email verified')}</Badge>
    ) : (
        <Badge variant="warning">{__('Email not verified')}</Badge>
    );
}

export default function RegistrationsIndex({ registrations }: Props) {
    const __ = useTranslate();

    const name = (registration: RegistrationRow) => (
        <Link
            href={show(registration.id)}
            className="text-ink hover:text-primary"
        >
            {registration.name}
        </Link>
    );
    const registered = (registration: RegistrationRow) =>
        registration.registered_at
            ? formatDateTime(registration.registered_at)
            : '—';

    const columns: ResponsiveColumn<RegistrationRow>[] = [
        { key: 'name', header: __('Applicant'), cell: name },
        {
            key: 'phone',
            header: __('Mobile'),
            cell: (registration) => registration.phone,
        },
        {
            key: 'email',
            header: __('Email'),
            cell: (registration) => registration.email,
            priority: 'low',
        },
        {
            key: 'email_verified',
            header: __('Email'),
            cell: (registration) => (
                <EmailState verified={registration.email_verified} />
            ),
        },
        { key: 'registered_at', header: __('Registered'), cell: registered },
    ];

    return (
        <>
            <Head title={__('Registrations')} />
            <ListTemplate
                header={{
                    title: __('Members'),
                    description: __(
                        'New sign-ups waiting for a decision, oldest first. Applicants have no access until approved.',
                    ),
                    children: <MemberTabs current="registrations" />,
                }}
                resultCount={
                    registrations.length === 1
                        ? __('1 registration')
                        : __(':count registrations', {
                              count: registrations.length,
                          })
                }
            >
                {registrations.length === 0 ? (
                    <p className="text-ink-muted">
                        {__('No registrations are waiting.')}
                    </p>
                ) : (
                    <ResponsiveTable
                        caption={__('Pending registrations')}
                        columns={columns}
                        rows={registrations}
                        rowKey={(registration) => registration.id}
                        card={(registration) => ({
                            title: name(registration),
                            status: (
                                <EmailState
                                    verified={registration.email_verified}
                                />
                            ),
                            meta: [
                                registration.phone,
                                registered(registration),
                            ],
                        })}
                    />
                )}
            </ListTemplate>
        </>
    );
}

RegistrationsIndex.layout = {
    breadcrumbs: [
        { title: 'Members', href: allMembers() },
        { title: 'Registrations', href: index() },
    ],
};
