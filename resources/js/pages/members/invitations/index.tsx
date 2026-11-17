import { Form, Head, router } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Pager } from '@/components/pager';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { FormDialog } from '@/components/form-dialog';
import { ListTemplate } from '@/components/templates/list-template';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { index as membersIndex } from '@/routes/members';
import { index, resend, revoke, store } from '@/routes/invitations';
import type { InvitationRow, InvitationStatusValue, Paginated } from '@/types';

type Props = {
    invitations: Paginated<InvitationRow>;
    defaultDays: number;
    can: { create: boolean; viewMembers: boolean };
};

const variants = {
    pending: 'warning',
    used: 'success',
    revoked: 'neutral',
    expired: 'neutral',
} as const satisfies Record<InvitationStatusValue, string>;

export default function InvitationsIndex({
    invitations,
    defaultDays,
    can,
}: Props) {
    const __ = useTranslate();
    const [inviting, setInviting] = useState(false);
    const [cancelling, setCancelling] = useState<InvitationRow | null>(null);

    const status = (row: InvitationRow) => (
        <Badge variant={variants[row.status]}>{row.status_label}</Badge>
    );
    const columns: ResponsiveColumn<InvitationRow>[] = [
        { key: 'email', header: __('Email'), cell: (row) => row.email },
        { key: 'status', header: __('Status'), cell: status },
        {
            key: 'expires',
            header: __('Expires'),
            cell: (row) =>
                row.status === 'used' && row.used_at
                    ? __('Accepted :date', {
                          date: formatDateTime(row.used_at),
                      })
                    : formatDateTime(row.expires_at),
        },
        {
            key: 'by',
            header: __('Invited by'),
            cell: (row) => row.invited_by,
            priority: 'low',
        },
        {
            key: 'actions',
            header: __('Actions'),
            align: 'right',
            cell: (row) => (
                <span className="relative flex justify-end gap-2">
                    {row.can.resend && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    resend.url(row.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {__('Resend')}
                        </Button>
                    )}
                    {row.can.revoke && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setCancelling(row)}
                        >
                            {__('Cancel')}
                        </Button>
                    )}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={__('Invitations')} />
            <ListTemplate
                header={{
                    title: __('Invitations'),
                    description: __(
                        'Invite someone by email. They fill in the member form from a link that works once and expires.',
                    ),
                    primaryAction: can.create
                        ? {
                              label: __('Invite member'),
                              icon: Send,
                              onClick: () => setInviting(true),
                          }
                        : undefined,
                }}
                resultCount={
                    invitations.total === 1
                        ? __('1 invitation')
                        : __(':count invitations', { count: invitations.total })
                }
                pagination={<Pager page={invitations} />}
            >
                {invitations.data.length === 0 ? (
                    <p className="text-ink-muted">
                        {__('No invitations have been sent yet.')}
                    </p>
                ) : (
                    <ResponsiveTable
                        caption={__('Invitations')}
                        columns={columns}
                        rows={invitations.data}
                        rowKey={(row) => row.id}
                        card={(row) => ({
                            title: row.email,
                            status: status(row),
                            meta: [
                                formatDateTime(row.expires_at),
                                row.invited_by,
                            ],
                        })}
                    />
                )}
            </ListTemplate>

            <Dialog open={inviting} onOpenChange={setInviting}>
                <DialogContent>
                    <DialogTitle>{__('Invite a member')}</DialogTitle>
                    <DialogDescription>
                        {__(
                            'They get an email with a link to fill in their details. They are approved automatically when they finish.',
                        )}
                    </DialogDescription>
                    <Form
                        noValidate
                        action={store()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setInviting(false)}
                        resetOnSuccess
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="invite-email">
                                        {__('Email address')}
                                    </Label>
                                    <Input
                                        id="invite-email"
                                        name="email"
                                        type="email"
                                        required
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="invite-days">
                                        {__('Link works for (days)')}
                                    </Label>
                                    <Input
                                        id="invite-days"
                                        name="expires_in_days"
                                        type="number"
                                        min={1}
                                        max={30}
                                        defaultValue={defaultDays}
                                        className="md:w-32"
                                    />
                                    <InputError
                                        message={errors.expires_in_days}
                                    />
                                </div>
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button type="button" variant="outline">
                                            {__('Cancel')}
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {__('Send invitation')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            {cancelling && (
                <FormDialog
                    open
                    onOpenChange={(open) => !open && setCancelling(null)}
                    title={__('Cancel this invitation?')}
                    description={__(
                        'The link sent to :email stops working. You can send a new one later.',
                        { email: cancelling.email },
                    )}
                    action={revoke(cancelling.id)}
                    submitLabel={__('Cancel invitation')}
                    destructive
                />
            )}
        </>
    );
}

InvitationsIndex.layout = {
    breadcrumbs: [
        { title: 'Members', href: membersIndex() },
        { title: 'Invitations', href: index() },
    ],
};
