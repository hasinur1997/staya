import { useForm } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import type { PageHeaderProps } from '@/components/page-header';
import { PermissionChecklist } from '@/components/permission-checklist';
import { FormTemplate } from '@/components/templates/form-template';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';
import type { RouteDefinition } from '@/wayfinder';
import type { PermissionGroup } from '@/types';

type Props = {
    header: Omit<PageHeaderProps, 'primaryAction' | 'mobileAction'>;
    action: RouteDefinition<'post' | 'put'>;
    cancelHref: NonNullable<InertiaLinkProps['href']>;
    submitLabel: string;
    groups: PermissionGroup[];
    initial: { name: string; permissions: string[] };
    canRename?: boolean;
    /** Set when permissions can't be changed; they are then not sent. */
    permissionsLockedReason?: string | null;
};

export function RoleForm({
    header,
    action,
    cancelHref,
    submitLabel,
    groups,
    initial,
    canRename = true,
    permissionsLockedReason = null,
}: Props) {
    const __ = useTranslate();
    const form = useForm(initial);

    form.transform(({ permissions, ...data }) =>
        permissionsLockedReason ? data : { ...data, permissions },
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.submit(action, { preserveScroll: true });
    };

    return (
        <FormTemplate
            header={header}
            formId="role-form"
            submitLabel={submitLabel}
            processing={form.processing}
            cancelHref={cancelHref}
        >
            <form
                id="role-form"
                onSubmit={submit}
                className="flex flex-col gap-4 md:gap-6"
            >
                <Card>
                    <fieldset className="flex flex-col gap-4 px-4">
                        <legend className="mb-4 text-subhead">
                            {__('Role')}
                        </legend>
                        <div className="flex flex-col gap-2 md:max-w-sm">
                            <Label htmlFor="name">{__('Name')}</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                readOnly={!canRename}
                                aria-describedby={
                                    canRename ? undefined : 'name-help'
                                }
                                required
                            />
                            {!canRename && (
                                <p
                                    id="name-help"
                                    className="text-small text-ink-muted"
                                >
                                    {__('System roles cannot be renamed.')}
                                </p>
                            )}
                            <InputError message={form.errors.name} />
                        </div>
                    </fieldset>
                </Card>

                {permissionsLockedReason && (
                    <Alert>
                        <AlertDescription>
                            {permissionsLockedReason}
                        </AlertDescription>
                    </Alert>
                )}
                <InputError message={form.errors.permissions} />

                <PermissionChecklist
                    groups={groups}
                    value={form.data.permissions}
                    onChange={(value) => form.setData('permissions', value)}
                    locked={Boolean(permissionsLockedReason)}
                />
            </form>
        </FormTemplate>
    );
}
