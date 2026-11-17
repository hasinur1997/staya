import { Form, Head } from '@inertiajs/react';
import {
    PurchaseFields,
    PurchaseTotalsCard,
    usePurchaseItems,
} from '@/components/purchase-form';
import type { PurchaseFormProps } from '@/components/purchase-form';
import { FormTemplate } from '@/components/templates/form-template';
import { useTranslate } from '@/lib/i18n';
import { create, index, store } from '@/routes/grocery';

type Props = PurchaseFormProps & {
    /** The user's own member id, when they may record their own. */
    ownMemberId: number | null;
};

export default function GroceryCreate({ ownMemberId, ...form }: Props) {
    const __ = useTranslate();
    const draft = usePurchaseItems();

    return (
        <>
            <Head title={__('New grocery purchase')} />
            <Form {...store.form()} id="purchase-form" disableWhileProcessing>
                {({ errors, processing }) => (
                    <FormTemplate
                        header={{
                            title: __('New grocery purchase'),
                            description: __(
                                'Add each item you bought and say who paid for it.',
                            ),
                        }}
                        formId="purchase-form"
                        submitLabel={__('Save purchase')}
                        processing={processing}
                        cancelHref={index()}
                        aside={
                            <PurchaseTotalsCard
                                totals={draft.totals}
                                pending={draft.pending}
                            />
                        }
                    >
                        <PurchaseFields
                            {...form}
                            {...draft}
                            ownMemberId={ownMemberId}
                            errors={errors}
                        />
                    </FormTemplate>
                )}
            </Form>
        </>
    );
}

GroceryCreate.layout = {
    breadcrumbs: [
        { title: 'Grocery purchases', href: index() },
        { title: 'New purchase', href: create() },
    ],
};
