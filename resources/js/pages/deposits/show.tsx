import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { DetailRow } from '@/components/detail-row';
import { Check, RotateCcw, X } from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import {
    DepositStatusBadge,
    depositNote,
} from '@/components/deposit-status-badge';
import { AuditHistoryCard } from '@/components/audit-history-card';
import { FormDialog } from '@/components/form-dialog';
import { DetailTemplate } from '@/components/templates/detail-template';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatBusinessDate, formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import {
    approve,
    cancel,
    index,
    reject,
    reverse,
    show,
} from '@/routes/deposits';
import { show as showMember } from '@/routes/members';
import type { AuditRow, DepositDetail, PageAction } from '@/types';

type Props = {
    deposit: DepositDetail;
    /** Audit history; null without `audit.view`. */
    history: AuditRow[] | null;
    can: {
        viewAny: boolean;
        cancel: boolean;
        approve: boolean;
        reject: boolean;
        reverse: boolean;
        viewMember: boolean;
    };
};

type OpenDialog = 'approve' | 'reject' | 'reverse' | 'cancel' | null;

export default function DepositsShow({ deposit, history, can }: Props) {
    const __ = useTranslate();
    const { errors } = usePage().props;
    const [dialog, setDialog] = useState<OpenDialog>(null);
    const closeDialog = (open: boolean) => !open && setDialog(null);
    const note = depositNote(deposit);

    setLayoutProps({
        breadcrumbs: [
            ...(can.viewAny ? [{ title: __('Deposits'), href: index() }] : []),
            {
                title: formatBusinessDate(deposit.deposit_date),
                href: show(deposit.id),
            },
        ],
    });

    const secondaryActions: PageAction[] = can.cancel
        ? [
              {
                  label: __('Cancel deposit'),
                  onClick: () => setDialog('cancel'),
              },
          ]
        : [];
    const menuActions: PageAction[] = can.reverse
        ? [
              {
                  label: __('Reverse'),
                  icon: RotateCcw,
                  destructive: true,
                  onClick: () => setDialog('reverse'),
              },
          ]
        : [];

    const pageErrors = Object.entries(errors ?? {})
        .filter(([field]) => field !== 'reason')
        .map(([, message]) => message);

    return (
        <>
            <Head title={__('Deposit')} />
            <DetailTemplate
                header={{
                    overline: __('Deposit'),
                    title: formatMoney(deposit.amount),
                    status: (
                        <DepositStatusBadge
                            status={deposit.status}
                            label={deposit.status_label}
                            selfApproved={deposit.is_self_approved}
                        />
                    ),
                    description: __(':member · paid :date', {
                        member: deposit.member_name,
                        date: formatBusinessDate(deposit.deposit_date),
                    }),
                    secondaryActions,
                    menuActions,
                }}
            >
                {pageErrors.length > 0 && (
                    <AlertError
                        title={__('This deposit could not be changed.')}
                        errors={pageErrors}
                    />
                )}
                {deposit.status === 'pending' &&
                    (can.approve || can.reject) && (
                        <Card className="border-success/40 bg-success-soft">
                            <CardContent className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                                <div className="flex flex-col gap-1">
                                    <p className="text-subhead text-ink">
                                        {__('Waiting for your decision')}
                                    </p>
                                    <p className="text-small text-ink-muted">
                                        {deposit.is_own
                                            ? __(
                                                  'This is your own deposit. Approving it is recorded as a self-approval.',
                                              )
                                            : __(
                                                  'Nothing counts towards the fund until you approve it.',
                                              )}
                                    </p>
                                </div>
                                <div className="flex gap-2 print:hidden">
                                    {can.reject && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="lg"
                                            onClick={() => setDialog('reject')}
                                        >
                                            <X />
                                            {__('Reject')}
                                        </Button>
                                    )}
                                    {can.approve && (
                                        <Button
                                            type="button"
                                            variant="success"
                                            size="lg"
                                            onClick={() => setDialog('approve')}
                                        >
                                            <Check />
                                            {__('Approve deposit')}
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-subhead">
                            {__('Details')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="flex flex-col gap-4">
                            <DetailRow label={__('Member')}>
                                {can.viewMember ? (
                                    <Link
                                        href={showMember(deposit.member_id)}
                                        className="text-primary hover:underline"
                                    >
                                        {deposit.member_name}
                                    </Link>
                                ) : (
                                    deposit.member_name
                                )}
                            </DetailRow>
                            <DetailRow label={__('Amount')}>
                                <span className="tabular-nums">
                                    {formatMoney(deposit.amount)}
                                </span>
                            </DetailRow>
                            <DetailRow label={__('Date paid')}>
                                {formatBusinessDate(deposit.deposit_date)}
                            </DetailRow>
                            <DetailRow label={__('Method')}>
                                {deposit.method_label}
                            </DetailRow>
                            <DetailRow label={__('Submitted')}>
                                {deposit.submitted_at
                                    ? __(':time by :name', {
                                          time: formatDateTime(
                                              deposit.submitted_at,
                                          ),
                                          name: deposit.submitted_by,
                                      })
                                    : deposit.submitted_by}
                            </DetailRow>
                            {deposit.reviewed_at && deposit.reviewed_by && (
                                <DetailRow
                                    label={
                                        deposit.status === 'rejected'
                                            ? __('Rejected')
                                            : __('Approved')
                                    }
                                >
                                    {__(':time by :name', {
                                        time: formatDateTime(
                                            deposit.reviewed_at,
                                        ),
                                        name: deposit.reviewed_by,
                                    })}
                                </DetailRow>
                            )}
                            {deposit.cancelled_at && (
                                <DetailRow label={__('Cancelled')}>
                                    {formatDateTime(deposit.cancelled_at)}
                                </DetailRow>
                            )}
                            {deposit.reversed_at && deposit.reversed_by && (
                                <DetailRow label={__('Reversed')}>
                                    {__(':time by :name', {
                                        time: formatDateTime(
                                            deposit.reversed_at,
                                        ),
                                        name: deposit.reversed_by,
                                    })}
                                </DetailRow>
                            )}
                            {note && (
                                <DetailRow label={__('Reason')}>
                                    {note}
                                </DetailRow>
                            )}
                        </dl>
                    </CardContent>
                </Card>
                {history && <AuditHistoryCard history={history} />}
            </DetailTemplate>

            <FormDialog
                open={dialog === 'approve'}
                onOpenChange={closeDialog}
                title={__('Approve :amount?', {
                    amount: formatMoney(deposit.amount),
                })}
                description={
                    deposit.is_own
                        ? __(
                              'This is your own deposit. The approval is recorded as a self-approval.',
                          )
                        : __(
                              'The fund balance goes up by this amount, counted in :month.',
                              {
                                  month: formatBusinessDate(
                                      deposit.deposit_date,
                                  ),
                              },
                          )
                }
                action={approve(deposit.id)}
                submitLabel={__('Approve')}
            />
            <FormDialog
                open={dialog === 'reject'}
                onOpenChange={closeDialog}
                title={__('Reject this deposit?')}
                description={__(
                    'The member sees this reason. The fund is not touched.',
                )}
                action={reject(deposit.id)}
                field={{ type: 'reason', name: 'reason', label: __('Reason') }}
                submitLabel={__('Reject deposit')}
                destructive
            />
            <FormDialog
                open={dialog === 'reverse'}
                onOpenChange={closeDialog}
                title={__('Reverse this deposit?')}
                description={__(
                    'A reversal is posted in the deposit’s month and the deposit stops counting. It cannot be undone; the member submits a new deposit instead.',
                )}
                action={reverse(deposit.id)}
                field={{ type: 'reason', name: 'reason', label: __('Reason') }}
                submitLabel={__('Reverse deposit')}
                destructive
            />
            <FormDialog
                open={dialog === 'cancel'}
                onOpenChange={closeDialog}
                title={__('Cancel this deposit?')}
                description={__(
                    'It was never counted, so nothing else changes.',
                )}
                action={cancel(deposit.id)}
                submitLabel={__('Cancel deposit')}
                destructive
            />
        </>
    );
}
