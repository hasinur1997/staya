import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { RotateCcw, Trash2, UserMinus } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/form-dialog';
import { AuditHistoryCard } from '@/components/audit-history-card';
import { MemberBalanceTab } from '@/components/member-balance-tab';
import { MemberDepositsCard } from '@/components/member-deposits-card';
import { MemberStaysCard } from '@/components/member-details';
import { MemberHero } from '@/components/member-hero';
import { MemberMealsTab } from '@/components/member-meals-tab';
import {
    FamilyDetailsCard,
    NidDetailsCard,
    PersonalDetailsCard,
    RegistrationCard,
} from '@/components/member-profile-cards';
import { MemberPurchasesCard } from '@/components/member-purchases-card';
import { PageStack } from '@/components/templates/page-grid';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { deactivate, destroy, index, reactivate, show } from '@/routes/members';
import type {
    AuditRow,
    DepositRow,
    MemberBalance,
    MemberMeals,
    MemberProfile,
    MemberTab,
    PageAction,
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
    /** The open tab. */
    tab: MemberTab;
    /** One month of meals; only loaded on the meals tab. */
    meals: MemberMeals | null;
    mealsVisible: boolean;
    /** Dues and what the fund owes; only loaded on the balance tab. */
    balance: MemberBalance | null;
    balanceVisible: boolean;
    /** Audit history; null without `audit.view`. */
    history: AuditRow[] | null;
    /** Today in Asia/Dhaka (Y-m-d). */
    today: string;
    can: {
        update: boolean;
        changeEmail: boolean;
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
    tab,
    deposits,
    purchases,
    meals,
    mealsVisible,
    balance,
    balanceVisible,
    history,
    today,
    can,
}: Props) {
    const __ = useTranslate();
    const [dialog, setDialog] = useState<OpenDialog>(null);
    const [deleting, setDeleting] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            ...(can.viewAny ? [{ title: __('Members'), href: index() }] : []),
            { title: member.name, href: show(member.id) },
        ],
    });

    const tabs: { key: MemberTab; label: string }[] = [
        { key: 'profile', label: __('Profile') },
        ...(mealsVisible
            ? [{ key: 'meals' as const, label: __('Meal sheet') }]
            : []),
        ...(purchases !== null
            ? [{ key: 'purchases' as const, label: __('Purchases') }]
            : []),
        ...(deposits !== null
            ? [{ key: 'deposits' as const, label: __('Deposits') }]
            : []),
        ...(balanceVisible
            ? [{ key: 'balance' as const, label: __('Balance') }]
            : []),
    ];

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
            <PageStack>
                <MemberHero
                    member={member}
                    canEdit={can.update}
                    actions={secondaryActions}
                    menuActions={menuActions}
                />

                <nav
                    aria-label={__('Member sections')}
                    className="-mt-2 flex gap-1 overflow-x-auto border-b border-border md:-mt-4 lg:-mt-8 print:hidden"
                >
                    {tabs.map((item) => (
                        <Link
                            key={item.key}
                            href={show.url(member.id, {
                                query: {
                                    tab:
                                        item.key === 'profile'
                                            ? undefined
                                            : item.key,
                                },
                            })}
                            preserveScroll
                            aria-current={item.key === tab ? 'page' : undefined}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 whitespace-nowrap text-ink-muted hover:bg-surface-raised',
                                item.key === tab
                                    ? 'border-primary text-primary'
                                    : 'border-transparent',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>

                {tab === 'profile' && (
                    <div className="flex flex-col gap-4 md:gap-6">
                        <div className="grid gap-4 md:grid-cols-2 md:gap-6">
                            <PersonalDetailsCard
                                member={member}
                                canEdit={can.update}
                                canChangeEmail={can.changeEmail}
                            />
                            <FamilyDetailsCard
                                member={member}
                                canEdit={can.update}
                            />
                            {can.viewNid && (
                                <NidDetailsCard
                                    member={member}
                                    nid={nid}
                                    canEdit={can.update}
                                />
                            )}
                            <RegistrationCard member={member} />
                        </div>
                        <MemberStaysCard member={member} />
                        {history && <AuditHistoryCard history={history} />}
                    </div>
                )}

                {tab === 'meals' && meals && (
                    <MemberMealsTab memberId={member.id} meals={meals} />
                )}

                {tab === 'purchases' && purchases && (
                    <MemberPurchasesCard purchases={purchases} />
                )}

                {tab === 'deposits' && deposits && (
                    <MemberDepositsCard deposits={deposits} />
                )}

                {tab === 'balance' && balance && (
                    <div className="flex flex-col gap-4 md:gap-6">
                        <MemberBalanceTab balance={balance} />
                    </div>
                )}
            </PageStack>

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
