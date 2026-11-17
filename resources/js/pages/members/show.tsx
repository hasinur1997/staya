import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Pencil, RotateCcw, Trash2, UserMinus } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/form-dialog';
import {
    MemberDetailsCard,
    MemberNidCard,
    MemberStaysCard,
    MemberSummaryCard,
} from '@/components/member-details';
import { MemberDepositsCard } from '@/components/member-deposits-card';
import { AuditHistoryCard } from '@/components/audit-history-card';
import { MemberMonthlyCard } from '@/components/member-monthly-card';
import { MemberPurchasesCard } from '@/components/member-purchases-card';
import { MemberStatusBadge } from '@/components/member-status-badge';
import { DetailTemplate } from '@/components/templates/detail-template';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import {
    deactivate,
    destroy,
    edit,
    index,
    reactivate,
    show,
} from '@/routes/members';
import type {
    AuditRow,
    DepositRow,
    MemberProfile,
    PageAction,
    MemberMonth,
    PurchaseRow,
} from '@/types';

type Props = {
    member: MemberProfile;
    /** Present only after the viewer asks to see it. */
    nid?: string;
    /** Latest deposits; null when the viewer may not see them. */
    deposits: DepositRow[] | null;
    /** Latest purchases made; null when the viewer may not see them. */
    purchases: PurchaseRow[] | null;
    /** Monthly due or credit; null when the viewer may not see it. */
    monthly: MemberMonth[] | null;
    /** Audit history; null without `audit.view`. */
    history: AuditRow[] | null;
    /** Today in Asia/Dhaka (Y-m-d). */
    today: string;
    can: {
        update: boolean;
        deactivate: boolean;
        reactivate: boolean;
        delete: boolean;
        viewNid: boolean;
        viewAny: boolean;
    };
};

type OpenDialog = 'deactivate' | 'reactivate' | 'delete' | null;

export default function MembersShow({
    member,
    nid,
    deposits,
    purchases,
    monthly,
    history,
    today,
    can,
}: Props) {
    const __ = useTranslate();
    const [dialog, setDialog] = useState<OpenDialog>(null);
    const [deleting, setDeleting] = useState(false);
    const currentStay = member.stays.find((stay) => stay.left_on === null);

    setLayoutProps({
        breadcrumbs: [
            ...(can.viewAny ? [{ title: __('Members'), href: index() }] : []),
            { title: member.name, href: show(member.id) },
        ],
    });

    const secondaryActions: PageAction[] = [
        ...(can.reactivate
            ? [
                  {
                      label: __('Reactivate'),
                      icon: RotateCcw,
                      onClick: () => setDialog('reactivate'),
                  },
              ]
            : []),
        ...(can.deactivate
            ? [
                  {
                      label: __('Deactivate'),
                      icon: UserMinus,
                      onClick: () => setDialog('deactivate'),
                  },
              ]
            : []),
    ];

    const menuActions: PageAction[] = can.delete
        ? [
              {
                  label: __('Delete'),
                  icon: Trash2,
                  destructive: true,
                  onClick: () => setDialog('delete'),
              },
          ]
        : [];

    const remove = () =>
        router.visit(destroy(member.id), {
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setDialog(null);
            },
        });

    const closeDialog = (open: boolean) => !open && setDialog(null);

    return (
        <>
            <Head title={member.name} />
            <DetailTemplate
                header={{
                    overline: __('Member'),
                    title: member.name,
                    status: (
                        <MemberStatusBadge
                            status={member.status}
                            label={member.status_label}
                        />
                    ),
                    description: currentStay
                        ? __('Living in the mess since :date', {
                              date: formatBusinessDate(currentStay.joined_on),
                          })
                        : undefined,
                    primaryAction: can.update
                        ? {
                              label: __('Edit'),
                              href: edit(member.id),
                              icon: Pencil,
                          }
                        : undefined,
                    secondaryActions,
                    menuActions,
                }}
                aside={<MemberSummaryCard member={member} />}
            >
                <MemberDetailsCard member={member} />
                {can.viewNid && <MemberNidCard member={member} nid={nid} />}
                {deposits && <MemberDepositsCard deposits={deposits} />}
                {monthly && <MemberMonthlyCard months={monthly} />}
                {purchases && <MemberPurchasesCard purchases={purchases} />}
                {history && <AuditHistoryCard history={history} />}
                <MemberStaysCard member={member} />
            </DetailTemplate>

            <FormDialog
                open={dialog === 'deactivate'}
                onOpenChange={closeDialog}
                title={__('Deactivate :name?', { name: member.name })}
                description={__(
                    'Their current stay ends on the leave date. They can still sign in and see their history, but cannot add purchases or deposits.',
                )}
                action={deactivate(member.id)}
                field={{
                    type: 'date',
                    name: 'left_on',
                    label: __('Left on'),
                    defaultValue: today,
                    max: today,
                }}
                submitLabel={__('Deactivate')}
            />

            <FormDialog
                open={dialog === 'reactivate'}
                onOpenChange={closeDialog}
                title={__('Reactivate :name?', { name: member.name })}
                description={__(
                    'A new stay starts on the join date. Earlier stays are kept as they are.',
                )}
                action={reactivate(member.id)}
                field={{
                    type: 'date',
                    name: 'joined_on',
                    label: __('Joined on'),
                    defaultValue: today,
                    max: today,
                }}
                submitLabel={__('Reactivate')}
            />

            <Dialog open={dialog === 'delete'} onOpenChange={closeDialog}>
                <DialogContent>
                    <DialogTitle>
                        {__('Delete :name?', { name: member.name })}
                    </DialogTitle>
                    <DialogDescription>
                        {__(
                            'The profile, stays and files are removed for good. Their account is deleted too unless other records refer to it.',
                        )}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">{__('Cancel')}</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={deleting}
                            onClick={remove}
                        >
                            {__('Delete member')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
