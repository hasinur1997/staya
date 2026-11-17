import { Form, Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import InputError from '@/components/input-error';
import { MemberFields } from '@/components/member-fields';
import { MemberStatusBadge } from '@/components/member-status-badge';
import { FormTemplate } from '@/components/templates/form-template';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';
import { edit, index, show, update } from '@/routes/members';
import type { MemberProfile } from '@/types';

type Props = {
    member: MemberProfile;
    /** Loaded on request; present only for those who may see NIDs. */
    nid?: string;
    can: { changeNid: boolean; changeEmail: boolean };
};

export default function MembersEdit({ member, nid, can }: Props) {
    const __ = useTranslate();
    const { mess } = usePage().props;

    setLayoutProps({
        breadcrumbs: [
            { title: __('Members'), href: index() },
            { title: member.name, href: show(member.id) },
            { title: __('Edit'), href: edit(member.id) },
        ],
    });

    // The NID number is only sent once it has been revealed, so an editor
    // who never asked to see it cannot change it by accident.
    const nidField = {
        show: can.changeNid && nid !== undefined,
        value: nid,
        fileRequired: false,
    };

    return (
        <>
            <Head title={__('Edit :name', { name: member.name })} />
            <Form
                noValidate
                {...update.form(member.id)}
                id="member-form"
                disableWhileProcessing
            >
                {({ errors, processing }) => (
                    <FormTemplate
                        header={{
                            title: member.name,
                            status: (
                                <MemberStatusBadge
                                    status={member.status}
                                    label={member.status_label}
                                />
                            ),
                            description: __(
                                'Changes to the name and email also update their account.',
                            ),
                        }}
                        formId="member-form"
                        submitLabel={__('Save changes')}
                        processing={processing}
                        cancelHref={show(member.id)}
                    >
                        <Card>
                            <fieldset className="flex flex-col gap-4 px-4">
                                <legend className="mb-4 text-title">
                                    {__('Account')}
                                </legend>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor="email">
                                        {__('Email address')}
                                    </Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        defaultValue={member.email}
                                        readOnly={!can.changeEmail}
                                        required
                                    />
                                    {!can.changeEmail && (
                                        <p className="text-caption text-ink-muted">
                                            {__(
                                                'Only someone allowed to edit this account can change its email.',
                                            )}
                                        </p>
                                    )}
                                    <InputError message={errors.email} />
                                </div>
                            </fieldset>
                        </Card>

                        <MemberFields
                            errors={errors}
                            member={member}
                            nid={nidField}
                            maxUploadKb={mess.maxUploadKb}
                            asCards
                        />
                        {can.changeNid && nid === undefined && (
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.reload({ only: ['nid'] })
                                    }
                                >
                                    <Eye aria-hidden />
                                    {__('Show NID fields')}
                                </Button>
                                <p className="text-caption text-ink-muted">
                                    {__(
                                        'Needed to change the NID number or copy. Viewing it is recorded in the audit log.',
                                    )}
                                </p>
                            </div>
                        )}
                    </FormTemplate>
                )}
            </Form>
        </>
    );
}
