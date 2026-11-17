import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { SettingsSection } from '@/components/settings-section';
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
import { reset } from '@/routes/mess-settings';

/** The phrase that must be typed; the server checks it again. */
const CONFIRMATION_PHRASE = 'RESET';

/**
 * Settings danger zone: erase all financial and meal data and start from
 * zero. Needs the exact phrase, a reason and the person's own password.
 */
export function ResetDataCard() {
    const __ = useTranslate();
    const [open, setOpen] = useState(false);
    const form = useForm({ confirmation: '', password: '', reason: '' });

    const close = (next: boolean) => {
        setOpen(next);

        if (!next) {
            form.reset();
            form.clearErrors();
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(reset.url(), {
            preserveScroll: true,
            onSuccess: () => close(false),
        });
    };

    return (
        <>
            <SettingsSection
                tone="danger"
                title={__('Danger zone')}
                description={__(
                    'Irreversible actions. Only people with the permission see this.',
                )}
            >
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex flex-col gap-1">
                        <h3 className="text-body-strong text-ink">
                            {__('Erase data and start from zero')}
                        </h3>
                        <p className="text-body text-ink-muted">
                            {__(
                                'Permanently deletes every deposit, grocery purchase, payable, ledger entry, closed month and meal, and sets the fund to ৳ 0.00. Members, users, roles, meal types and settings are kept. This cannot be undone.',
                            )}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="destructive"
                        className="shrink-0"
                        onClick={() => setOpen(true)}
                    >
                        {__('Erase data')}
                    </Button>
                </div>
            </SettingsSection>

            <Dialog open={open} onOpenChange={close}>
                <DialogContent>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogTitle>
                            {__('Erase all financial and meal data?')}
                        </DialogTitle>
                        <DialogDescription>
                            {__(
                                'Everything about money and meals is deleted for good. A record of who did this, why and how much was erased stays in the audit log.',
                            )}
                        </DialogDescription>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="reset_reason" required>
                                {__('Reason')}
                            </Label>
                            <Textarea
                                id="reset_reason"
                                rows={2}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.reason} />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="reset_confirmation" required>
                                {__('Type :phrase to confirm', {
                                    phrase: CONFIRMATION_PHRASE,
                                })}
                            </Label>
                            <Input
                                id="reset_confirmation"
                                value={form.data.confirmation}
                                onChange={(event) =>
                                    form.setData(
                                        'confirmation',
                                        event.target.value,
                                    )
                                }
                                autoComplete="off"
                                data-keep-digits
                                required
                            />
                            <InputError message={form.errors.confirmation} />
                        </div>

                        <div className="flex flex-col gap-2">
                            <Label htmlFor="reset_password" required>
                                {__('Your password')}
                            </Label>
                            <PasswordInput
                                id="reset_password"
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                autoComplete="current-password"
                                required
                            />
                            <InputError message={form.errors.password} />
                        </div>

                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="outline">
                                    {__('Cancel')}
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={
                                    form.processing ||
                                    form.data.confirmation !==
                                        CONFIRMATION_PHRASE
                                }
                            >
                                {__('Erase everything')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
