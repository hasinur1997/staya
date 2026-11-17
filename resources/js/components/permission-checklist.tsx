import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';
import type { PermissionGroup } from '@/types';

type Props = {
    groups: PermissionGroup[];
    value: string[];
    onChange: (value: string[]) => void;
    /** The whole checklist is read-only (e.g. the Admin role). */
    locked?: boolean;
};

/**
 * One fieldset card per catalog resource. A permission the viewer lacks can
 * be removed but not added; the server enforces the same rule.
 */
export function PermissionChecklist({
    groups,
    value,
    onChange,
    locked = false,
}: Props) {
    const __ = useTranslate();

    const toggle = (name: string, checked: boolean) =>
        onChange(
            checked
                ? [...value, name]
                : value.filter((permission) => permission !== name),
        );

    return (
        <>
            {groups.map((group) => (
                <Card key={group.group}>
                    <fieldset className="flex flex-col gap-4 px-4">
                        <legend className="mb-4 text-title">
                            {group.label}
                        </legend>
                        {group.permissions.map((permission) => {
                            const id = `permission-${permission.name}`;
                            const checked = value.includes(permission.name);
                            const disabled =
                                locked || (!permission.grantable && !checked);

                            return (
                                <div
                                    key={permission.name}
                                    className="flex items-start gap-3"
                                >
                                    <Checkbox
                                        id={id}
                                        checked={checked}
                                        disabled={disabled}
                                        onCheckedChange={(state) =>
                                            toggle(
                                                permission.name,
                                                state === true,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                    <div className="flex min-w-0 flex-col gap-1">
                                        <Label htmlFor={id}>
                                            {permission.description ??
                                                permission.name}
                                        </Label>
                                        <span className="font-mono text-caption text-neutral">
                                            {permission.name}
                                        </span>
                                        {!permission.grantable && !locked && (
                                            <span className="text-caption text-ink-muted">
                                                {__(
                                                    "You don't hold this permission",
                                                )}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </fieldset>
                </Card>
            ))}
        </>
    );
}
