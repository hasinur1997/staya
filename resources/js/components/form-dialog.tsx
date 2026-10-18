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
    | { type: 'reason'; name: string; label: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    action: RouteDefinition<'post'>;
    field: Field;
    submitLabel: string;
    destructive?: boolean;
};

/**
 * A confirmation dialog that posts one field (a date or a reason). Field
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
    const id = `dialog-${field.name}`;

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
                            <div className="flex flex-col gap-2">
                                <Label htmlFor={id}>{field.label}</Label>
                                {field.type === 'date' ? (
                                    <Input
                                        id={id}
                                        name={field.name}
                                        type="date"
                                        defaultValue={field.defaultValue}
                                        max={field.max}
                                        required
                                    />
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
