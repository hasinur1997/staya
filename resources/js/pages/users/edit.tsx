import { Head, router, setLayoutProps } from '@inertiajs/react';
import { RotateCcw, UserX } from 'lucide-react';
import { useState } from 'react';
import { UserForm } from '@/components/user-form';
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
import { formatDateTime } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { deactivate, edit, index, reactivate, update } from '@/routes/users';
import type { EditableUser, PageAction, RoleOption } from '@/types';

type Props = {
    user: EditableUser;
    roles: RoleOption[];
    can: { assignRoles: boolean; deactivate: boolean; reactivate: boolean };
};

export default function UsersEdit({ user, roles, can }: Props) {
    const __ = useTranslate();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: __('Users'), href: index() },
            { title: user.name, href: edit(user.id) },
        ],
    });

    const post = (target: typeof deactivate | typeof reactivate) =>
        router.visit(target(user.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirming(false);
            },
        });

    const secondaryActions: PageAction[] = can.reactivate
        ? [
              {
                  label: __('Reactivate'),
                  icon: RotateCcw,
                  onClick: () => post(reactivate),
                  disabled: processing,
              },
          ]
        : [];
    const menuActions: PageAction[] = can.deactivate
        ? [
              {
                  label: __('Deactivate'),
                  icon: UserX,
                  destructive: true,
                  onClick: () => setConfirming(true),
              },
          ]
        : [];

    return (
        <>
            <Head title={user.name} />
            <UserForm
                key={user.id}
                header={{
                    title: user.name,
                    status: user.is_active ? (
                        <Badge variant="success">{__('Active')}</Badge>
                    ) : (
                        <Badge variant="neutral">{__('Deactivated')}</Badge>
                    ),
                    description: user.last_login_at
                        ? __('Last signed in :time', {
                              time: formatDateTime(user.last_login_at),
                          })
                        : __('Never signed in'),
                    secondaryActions,
                    menuActions,
                }}
                action={update(user.id)}
                cancelHref={index()}
                submitLabel={__('Save changes')}
                roles={roles}
                initial={{
                    name: user.name,
                    email: user.email,
                    roles: user.role_ids,
                }}
                rolesLockedReason={
                    can.assignRoles
                        ? null
                        : __('You cannot change your own roles.')
                }
            />

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogTitle>
                        {__('Deactivate :name?', { name: user.name })}
                    </DialogTitle>
                    <DialogDescription>
                        {__(
                            'They will be signed out everywhere and cannot sign in until reactivated. Their history stays intact.',
                        )}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">{__('Cancel')}</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => post(deactivate)}
                        >
                            {__('Deactivate')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
