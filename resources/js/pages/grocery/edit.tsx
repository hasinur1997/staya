import { Form, Head, setLayoutProps } from '@inertiajs/react';
import {
    PurchaseFields,
    PurchaseTotalsCard,
    usePurchaseItems,
} from '@/components/purchase-form';
import type { PurchaseFormProps } from '@/components/purchase-form';
import { PurchaseStatusBadge } from '@/components/purchase-status-badge';
import { FormTemplate } from '@/components/templates/form-template';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { edit, index, show, update } from '@/routes/grocery';
import type { PurchaseDetail } from '@/types';

type Props = PurchaseFormProps & {
    purchase: PurchaseDetail;
    /** The server's time when the page was built (ISO 8601). */
    serverNow: string;
};

export default function GroceryEdit({ purchase, serverNow, ...form }: Props) {
    const __ = useTranslate();
    const draft = usePurchaseItems(purchase);
    const title = __(':date purchase', {
        date: formatBusinessDate(purchase.purchase_date),
    });

    setLayoutProps({
        breadcrumbs: [
            { title: __('Grocery purchases'), href: index() },
            { title, href: show(purchase.id) },
            { title: __('Edit'), href: edit(purchase.id) },
        ],
    });

    return (
        <>
            <Head title={__('Edit purchase')} />
            <Form
                {...update.form(purchase.id)}
                id="purchase-form"
                disableWhileProcessing
            >
                {({ errors, processing }) => (
                    <FormTemplate
                        header={{
                            title: __('Edit purchase'),
                            description: __(
                                'Saving reverses the old ledger amount and posts the new one.',
                            ),
                        }}
                        formId="purchase-form"
                        submitLabel={__('Save changes')}
                        processing={processing}
                        cancelHref={show(purchase.id)}
                        aside={
                            <PurchaseTotalsCard
                                totals={draft.totals}
                                pending={draft.pending}
                            >
                                <PurchaseStatusBadge
                                    purchase={purchase}
                                    serverNow={serverNow}
                                />
                            </PurchaseTotalsCard>
                        }
                    >
                        <PurchaseFields
                            {...form}
                            {...draft}
                            purchase={purchase}
                            errors={errors}
                        />
                    </FormTemplate>
                )}
            </Form>
        </>
    );
}
