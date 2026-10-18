import InputError from '@/components/input-error';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslate } from '@/lib/i18n';
import type { MemberProfile } from '@/types';

type Errors = Partial<Record<string, string>>;

type Props = {
    errors: Errors;
    /** Current values when editing; empty for a new member. */
    member?: Pick<
        MemberProfile,
        | 'first_name'
        | 'last_name'
        | 'phone'
        | 'father_name'
        | 'mother_name'
        | 'permanent_address'
    >;
    /** The NID number field; hidden when the viewer may not see NIDs. */
    nid?: { show: boolean; value?: string; fileRequired: boolean };
    maxUploadKb: number;
    /** Wrap each group in a Card (staff forms) or not (sign-up card). */
    asCards?: boolean;
};

function Field({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-small text-ink-muted">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

function Group({
    legend,
    asCard,
    children,
}: {
    legend: string;
    asCard: boolean;
    children: React.ReactNode;
}) {
    const fieldset = (
        <fieldset
            className={
                asCard ? 'flex flex-col gap-4 px-4' : 'flex flex-col gap-4'
            }
        >
            <legend className="mb-4 text-subhead">{legend}</legend>
            {children}
        </fieldset>
    );

    return asCard ? <Card>{fieldset}</Card> : fieldset;
}

/**
 * The member form's personal, family and document fields, as plain named
 * inputs for an Inertia <Form>. Shared by sign-up and the staff forms; the
 * server validates everything again.
 */
export function MemberFields({
    errors,
    member,
    nid = { show: true, fileRequired: true },
    maxUploadKb,
    asCards = false,
}: Props) {
    const __ = useTranslate();
    const maxMb = Math.round((maxUploadKb / 1024) * 10) / 10;
    const uploadHint = (types: string) =>
        __(':types, up to :size MB', { types, size: maxMb });

    return (
        <>
            <Group legend={__('Personal details')} asCard={asCards}>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        id="first_name"
                        label={__('First name')}
                        error={errors.first_name}
                    >
                        <Input
                            id="first_name"
                            name="first_name"
                            defaultValue={member?.first_name}
                            autoComplete="given-name"
                            required
                        />
                    </Field>
                    <Field
                        id="last_name"
                        label={__('Last name')}
                        error={errors.last_name}
                    >
                        <Input
                            id="last_name"
                            name="last_name"
                            defaultValue={member?.last_name}
                            autoComplete="family-name"
                            required
                        />
                    </Field>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        id="phone"
                        label={__('Mobile number')}
                        error={errors.phone}
                        hint={__('A Bangladeshi number, such as 01712345678')}
                    >
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            inputMode="tel"
                            defaultValue={member?.phone}
                            autoComplete="tel"
                            required
                        />
                    </Field>
                    {nid.show && (
                        <Field
                            id="nid_number"
                            label={__('NID number')}
                            error={errors.nid_number}
                            hint={__('10, 13 or 17 digits')}
                        >
                            <Input
                                id="nid_number"
                                name="nid_number"
                                inputMode="numeric"
                                defaultValue={nid.value}
                                autoComplete="off"
                                required
                            />
                        </Field>
                    )}
                </div>
            </Group>

            <Group legend={__('Family and address')} asCard={asCards}>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        id="father_name"
                        label={__("Father's name")}
                        error={errors.father_name}
                    >
                        <Input
                            id="father_name"
                            name="father_name"
                            defaultValue={member?.father_name}
                            required
                        />
                    </Field>
                    <Field
                        id="mother_name"
                        label={__("Mother's name")}
                        error={errors.mother_name}
                    >
                        <Input
                            id="mother_name"
                            name="mother_name"
                            defaultValue={member?.mother_name}
                            required
                        />
                    </Field>
                </div>
                <Field
                    id="permanent_address"
                    label={__('Permanent address')}
                    error={errors.permanent_address}
                >
                    <Textarea
                        id="permanent_address"
                        name="permanent_address"
                        defaultValue={member?.permanent_address}
                        rows={3}
                        required
                    />
                </Field>
            </Group>

            <Group legend={__('Documents')} asCard={asCards}>
                <div className="grid gap-4 md:grid-cols-2">
                    {nid.show && (
                        <Field
                            id="nid_file"
                            label={
                                nid.fileRequired
                                    ? __('NID copy')
                                    : __('Replace NID copy')
                            }
                            error={errors.nid_file}
                            hint={uploadHint('PDF, JPG or PNG')}
                        >
                            <Input
                                id="nid_file"
                                name="nid_file"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                required={nid.fileRequired}
                            />
                        </Field>
                    )}
                    <Field
                        id="photo"
                        label={
                            member
                                ? __('Replace photo')
                                : __('Photo (optional)')
                        }
                        error={errors.photo}
                        hint={uploadHint('JPG, PNG or WebP')}
                    >
                        <Input
                            id="photo"
                            name="photo"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                        />
                    </Field>
                </div>
            </Group>
        </>
    );
}
