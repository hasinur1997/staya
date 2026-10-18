import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';
import type { RoleOption } from '@/types';

type Props = {
    roles: RoleOption[];
    value: number[];
    onChange: (value: number[]) => void;
    /** Why every box is read-only, e.g. editing your own account. */
    lockedReason?: string | null;
};

/** Roles to assign. Roles holding permissions the viewer lacks are locked. */
export function RoleChecklist({ roles, value, onChange, lockedReason }: Props) {
    const __ = useTranslate();

    const toggle = (id: number, checked: boolean) =>
        onChange(
            checked ? [...value, id] : value.filter((role) => role !== id),
        );

    return (
        <Card>
            <fieldset className="flex flex-col gap-4 px-4">
                <legend className="mb-4 text-subhead">{__('Roles')}</legend>
                {lockedReason && (
                    <p className="text-small text-ink-muted">{lockedReason}</p>
                )}
                {roles.map((role) => {
                    const id = `role-${role.id}`;

                    return (
                        <div key={role.id} className="flex items-start gap-3">
                            <Checkbox
                                id={id}
                                checked={value.includes(role.id)}
                                disabled={
                                    Boolean(lockedReason) || !role.assignable
                                }
                                onCheckedChange={(state) =>
                                    toggle(role.id, state === true)
                                }
                                className="mt-1"
                            />
                            <div className="flex min-w-0 flex-col gap-1">
                                <div className="flex items-center gap-2">
                                    <Label htmlFor={id}>{role.name}</Label>
                                    {role.is_system && (
                                        <Badge variant="neutral">
                                            {__('System')}
                                        </Badge>
                                    )}
                                </div>
                                {!role.assignable && !lockedReason && (
                                    <span className="text-caption text-ink-muted">
                                        {__(
                                            "Includes permissions you don't hold",
                                        )}
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                })}
            </fieldset>
        </Card>
    );
}
