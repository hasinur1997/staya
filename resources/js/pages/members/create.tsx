import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { MemberFields } from '@/components/member-fields';
import PasswordInput from '@/components/password-input';
import { FormTemplate } from '@/components/templates/form-template';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslate } from '@/lib/i18n';
import { create, index, store } from '@/routes/members';
import type { LinkableUser } from '@/types';

type Props = {
    linkableUsers: LinkableUser[];
    /** Today in Asia/Dhaka (Y-m-d); the latest allowed join date. */
    today: string;
    passwordRules: string;
};

type AccountMode = 'new' | 'existing';

export default function MembersCreate({
    linkableUsers,
    today,
    passwordRules,
}: Props) {
    const __ = useTranslate();
    const { mess } = usePage().props;
    const [mode, setMode] = useState<AccountMode>('new');
    const [userId, setUserId] = useState('');

    return (
        <>
            <Head title={__('New member')} />
            <Form
                {...store.form()}
                id="member-form"
                transform={(data) => ({ ...data, account: mode })}
                disableWhileProcessing
            >
                {({ errors, processing }) => (
                    <FormTemplate
                        header={{
                            title: __('New member'),
                            description: __(
                                'Registered members are approved straight away and get the Member role.',
                            ),
                        }}
                        formId="member-form"
                        submitLabel={__('Register member')}
                        processing={processing}
                        cancelHref={index()}
                    >
                        <Card>
                            <fieldset className="flex flex-col gap-4 px-4">
                                <legend className="mb-4 text-subhead">
                                    {__('Account')}
                                </legend>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    value={mode}
                                    onValueChange={(value) =>
                                        value && setMode(value as AccountMode)
                                    }
                                    aria-label={__('Account')}
                                >
                                    <ToggleGroupItem value="new">
                                        {__('Create an account')}
                                    </ToggleGroupItem>
                                    <ToggleGroupItem
                                        value="existing"
                                        disabled={linkableUsers.length === 0}
                                    >
                                        {__('Link an existing user')}
                                    </ToggleGroupItem>
                                </ToggleGroup>

                                {mode === 'new' ? (
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="flex flex-col gap-2 md:col-span-2">
                                            <Label htmlFor="email">
                                                {__('Email address')}
                                            </Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                autoComplete="off"
                                                required
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="password">
                                                {__('Password')}
                                            </Label>
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                autoComplete="new-password"
                                                passwordrules={passwordRules}
                                                required
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="password_confirmation">
                                                {__('Confirm password')}
                                            </Label>
                                            <PasswordInput
                                                id="password_confirmation"
                                                name="password_confirmation"
                                                autoComplete="new-password"
                                                required
                                            />
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="user_id">
                                            {__('User')}
                                        </Label>
                                        <Select
                                            name="user_id"
                                            value={userId}
                                            onValueChange={setUserId}
                                        >
                                            <SelectTrigger id="user_id">
                                                <SelectValue
                                                    placeholder={__(
                                                        'Choose a user',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {linkableUsers.map((user) => (
                                                    <SelectItem
                                                        key={user.id}
                                                        value={String(user.id)}
                                                    >
                                                        {user.name} (
                                                        {user.email})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-small text-ink-muted">
                                            {__(
                                                'Accounts without a member profile. Their name is replaced by the member name.',
                                            )}
                                        </p>
                                        <InputError message={errors.user_id} />
                                    </div>
                                )}
                                <InputError message={errors.account} />
                            </fieldset>
                        </Card>

                        <MemberFields
                            errors={errors}
                            maxUploadKb={mess.maxUploadKb}
                            asCards
                        />

                        <Card>
                            <fieldset className="flex flex-col gap-4 px-4">
                                <legend className="mb-4 text-subhead">
                                    {__('Stay')}
                                </legend>
                                <div className="flex flex-col gap-2 md:w-1/2">
                                    <Label htmlFor="joined_on">
                                        {__('Joined on')}
                                    </Label>
                                    <Input
                                        id="joined_on"
                                        name="joined_on"
                                        type="date"
                                        defaultValue={today}
                                        max={today}
                                        required
                                    />
                                    <InputError message={errors.joined_on} />
                                </div>
                            </fieldset>
                        </Card>
                    </FormTemplate>
                )}
            </Form>
        </>
    );
}

MembersCreate.layout = {
    breadcrumbs: [
        { title: 'Members', href: index() },
        { title: 'New member', href: create() },
    ],
};
