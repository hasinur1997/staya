import { DatePicker } from '@/components/ui/date-picker';
import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import { useTranslate } from '@/lib/i18n';
import type { RouteDefinition } from '@/wayfinder';

type Field =
    | {
          type: 'date';
          name: string;
          label: string;
          defaultValue: string;
          max: string;
      }
    | { type: 'reason'; name: string; label: string }
    | {
          /** A money amount typed as a decimal string. */
          type: 'amount';
          name: string;
          label: string;
          defaultValue?: string;
          help?: string;
      };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    action: RouteDefinition<'post'>;
    /** The one field to post; a plain confirmation when left out. */
    field?: Field;
    submitLabel: string;
    destructive?: boolean;
};

/**
 * A confirmation dialog that posts one field (a date, a reason or an
 * amount). Field
 * errors from the server show inside the dialog; it closes on success.
 */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    action,
    field,
    submitLabel,
    destructive = false,
}: Props) {
    const __ = useTranslate();
    const id = `dialog-${field?.name ?? 'confirm'}`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <Form
                    action={action}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {field && (
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor={id} required>
                                        {field.label}
                                    </Label>
                                    {field.type === 'date' ? (
                                        <DatePicker
                                            id={id}
                                            name={field.name}
                                            defaultValue={field.defaultValue}
                                            max={field.max}
                                        />
                                    ) : field.type === 'amount' ? (
                                        <>
                                            <Input
                                                id={id}
                                                name={field.name}
                                                inputMode="decimal"
                                                pattern="\d+(\.\d{1,2})?"
                                                defaultValue={
                                                    field.defaultValue
                                                }
                                                placeholder="0.00"
                                                className="text-right tabular-nums"
                                                required
                                            />
                                            {field.help && (
                                                <p className="text-small text-ink-muted">
                                                    {field.help}
                                                </p>
                                            )}
                                        </>
                                    ) : (
                                        <Textarea
                                            id={id}
                                            name={field.name}
                                            rows={3}
                                            required
                                        />
                                    )}
                                    <InputError message={errors[field.name]} />
                                </div>
                            )}
                            {Object.values(errors).length > 0 && !field && (
                                <InputError
                                    message={Object.values(errors)[0]}
                                />
                            )}
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        {__('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant={
                                        destructive ? 'destructive' : 'default'
                                    }
                                    disabled={processing}
                                >
                                    {submitLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
