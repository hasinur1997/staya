import { Head, router, setLayoutProps } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { RoleForm } from '@/components/role-form';
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
import { useTranslate } from '@/lib/i18n';
import { destroy, edit, index, update } from '@/routes/roles';
import type { EditableRole, PageAction, PermissionGroup } from '@/types';

type Props = {
    role: EditableRole;
    groups: PermissionGroup[];
    can: { rename: boolean; editPermissions: boolean; delete: boolean };
    lockReason: string | null;
};

export default function RolesEdit({ role, groups, can, lockReason }: Props) {
    const __ = useTranslate();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);

    setLayoutProps({
        breadcrumbs: [
            { title: __('Roles'), href: index() },
            { title: role.name, href: edit(role.id) },
        ],
    });

    const menuActions: PageAction[] = can.delete
        ? [
              {
                  label: __('Delete role'),
                  icon: Trash2,
                  destructive: true,
                  onClick: () => setConfirming(true),
              },
          ]
        : [];

    const remove = () =>
        router.visit(destroy(role.id), {
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirming(false);
            },
        });

    return (
        <>
            <Head title={role.name} />
            <RoleForm
                key={role.id}
                header={{
                    title: role.name,
                    status: role.is_system ? (
                        <Badge variant="neutral">{__('System')}</Badge>
                    ) : undefined,
                    description: __('Assigned to :count users', {
                        count: role.users_count,
                    }),
                    menuActions,
                }}
                action={update(role.id)}
                cancelHref={index()}
                submitLabel={__('Save changes')}
                groups={groups}
                initial={{ name: role.name, permissions: role.permissions }}
                canRename={can.rename}
                permissionsLockedReason={
                    can.editPermissions ? null : lockReason
                }
            />

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogTitle>
                        {__('Delete :name?', { name: role.name })}
                    </DialogTitle>
                    <DialogDescription>
                        {__(
                            'The role is removed for good. The audit log keeps a record of it.',
                        )}
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">{__('Cancel')}</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={remove}
                        >
                            {__('Delete role')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
