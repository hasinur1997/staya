import { Combobox } from '@/components/ui/combobox';
import { DatePicker } from '@/components/ui/date-picker';
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { FormTemplate } from '@/components/templates/form-template';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';
import { create, index, store } from '@/routes/deposits';
import type { MemberOption } from '@/types';

type Props = {
    /** Today in Asia/Dhaka (Y-m-d): the latest allowed date. */
    today: string;
    fundStartDate: string | null;
    /** Members staff may record for; empty without create_for_others. */
    members: MemberOption[];
    /** The user's own member id, when they may submit their own. */
    ownMemberId: number | null;
    can: { createForOthers: boolean; approve: boolean; approveOwn: boolean };
};

export default function DepositsCreate({
    today,
    fundStartDate,
    members,
    ownMemberId,
    can,
}: Props) {
    const __ = useTranslate();
    const [memberId, setMemberId] = useState(
        ownMemberId ? String(ownMemberId) : '',
    );
    const forOwn = memberId !== '' && Number(memberId) === ownMemberId;
    const canApproveOnSave = can.approve && (!forOwn || can.approveOwn);
    const [approve, setApprove] = useState(true);

    return (
        <>
            <Head title={__('New deposit')} />
            <Form
                noValidate
                {...store.form()}
                id="deposit-form"
                transform={(data) => ({
                    ...data,
                    ...(can.createForOthers ? { member_id: memberId } : {}),
                    approve: canApproveOnSave && approve,
                })}
                disableWhileProcessing
            >
                {({ errors, processing }) => (
                    <FormTemplate
                        header={{
                            title: can.createForOthers
                                ? __('Record a deposit')
                                : __('Submit a deposit'),
                            description: __(
                                'Cash only. A deposit counts towards the fund once it is approved.',
                            ),
                        }}
                        formId="deposit-form"
                        submitLabel={
                            canApproveOnSave && approve
                                ? __('Save and approve')
                                : __('Submit for approval')
                        }
                        processing={processing}
                        cancelHref={index()}
                    >
                        <Card>
                            <fieldset className="flex flex-col gap-4 px-4">
                                <legend className="mb-4 text-title">
                                    {__('Deposit')}
                                </legend>

                                {can.createForOthers && (
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="member_id" required>
                                            {__('Member')}
                                        </Label>
                                        <Combobox
                                            id="member_id"
                                            value={memberId}
                                            onValueChange={setMemberId}
                                            options={members.map((member) => ({
                                                value: String(member.id),
                                                label:
                                                    member.id === ownMemberId
                                                        ? __(':name (you)', {
                                                              name: member.name,
                                                          })
                                                        : member.name,
                                            }))}
                                            placeholder={__('Choose a member')}
                                            searchPlaceholder={__('Search')}
                                            emptyText={__('Nothing found.')}
                                            className="md:w-1/2"
                                            aria-invalid={Boolean(
                                                errors.member_id,
                                            )}
                                        />
                                        <InputError
                                            message={errors.member_id}
                                        />
                                    </div>
                                )}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="amount">
                                            {__('Amount (৳)')}
                                        </Label>
                                        <Input
                                            id="amount"
                                            name="amount"
                                            inputMode="decimal"
                                            pattern="\d+(\.\d{1,2})?"
                                            placeholder="0.00"
                                            className="text-right tabular-nums"
                                            required
                                        />
                                        <InputError message={errors.amount} />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="deposit_date" required>
                                            {__('Date paid')}
                                        </Label>
                                        <DatePicker
                                            id="deposit_date"
                                            name="deposit_date"
                                            defaultValue={today}
                                            max={today}
                                            min={fundStartDate}
                                            aria-invalid={Boolean(
                                                errors.deposit_date,
                                            )}
                                        />
                                        <InputError
                                            message={errors.deposit_date}
                                        />
                                    </div>
                                </div>

                                <div className="flex flex-col gap-2">
                                    <Label>{__('Method')}</Label>
                                    <p className="text-ink">{__('Cash')}</p>
                                </div>

                                {canApproveOnSave && (
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id="approve"
                                            checked={approve}
                                            onCheckedChange={(checked) =>
                                                setApprove(checked === true)
                                            }
                                        />
                                        <div className="flex flex-col gap-1">
                                            <Label htmlFor="approve">
                                                {__('Approve on save')}
                                            </Label>
                                            <p className="text-caption text-ink-muted">
                                                {forOwn
                                                    ? __(
                                                          'This is your own deposit, so the approval is flagged as a self-approval.',
                                                      )
                                                    : __(
                                                          'Use this when you have the cash in hand. The fund balance updates straight away.',
                                                      )}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </fieldset>
                        </Card>
                    </FormTemplate>
                )}
            </Form>
        </>
    );
}

DepositsCreate.layout = {
    breadcrumbs: [
        { title: 'Deposits', href: index() },
        { title: 'New deposit', href: create() },
    ],
};
