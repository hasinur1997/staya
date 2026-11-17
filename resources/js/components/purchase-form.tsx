import { usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { DraftItem } from '@/hooks/use-purchase-draft';
import {
    useItemNameSuggestions,
    usePurchaseTotals,
} from '@/hooks/use-purchase-draft';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { receipt as receiptRoute } from '@/routes/grocery';
import type {
    GroceryUnitValue,
    MemberOption,
    Option,
    PurchaseDetail,
    PurchaseTotals,
} from '@/types';

export type PurchaseFormProps = {
    /** Today in Asia/Dhaka (Y-m-d): the latest allowed date. */
    today: string;
    fundStartDate: string | null;
    units: Option<GroceryUnitValue>[];
    /** Members who can be the buyer; empty unless the user may choose. */
    buyers: MemberOption[];
    can: { chooseBuyer: boolean };
};

type Props = PurchaseFormProps & {
    /** The purchase being edited; left out for a new one. */
    purchase?: PurchaseDetail;
    /** The user's own member id, preselected as the buyer. */
    ownMemberId?: number | null;
    errors: Record<string, string>;
};

let nextKey = 0;

function blankItem(): DraftItem {
    return {
        key: nextKey++,
        item_name: '',
        quantity: '',
        unit: 'kg',
        unit_price: '',
    };
}

function Field({
    id,
    label,
    help,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    help?: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={`flex flex-col gap-2 ${className ?? ''}`}>
            <Label htmlFor={id}>{label}</Label>
            {children}
            {help && <p className="text-small text-ink-muted">{help}</p>}
            <InputError message={error} />
        </div>
    );
}

/**
 * State for the line items of a purchase form, with the server's totals
 * for them. Shared by the fields and the totals panel.
 */
export function usePurchaseItems(purchase?: PurchaseDetail) {
    const [items, setItems] = useState<DraftItem[]>(() =>
        purchase
            ? purchase.items.map((item) => ({
                  key: nextKey++,
                  item_name: item.item_name,
                  // "2.500" reads better as "2.5" in the field.
                  quantity: item.quantity.replace(/\.?0+$/, ''),
                  unit: item.unit,
                  unit_price: item.unit_price,
              }))
            : [blankItem()],
    );
    const { totals, pending } = usePurchaseTotals(items);

    return { items, setItems, totals, pending };
}

/**
 * The fields of the purchase form, as named inputs for an Inertia <Form>.
 * Line totals come from the server; the browser never adds anything up.
 */
export function PurchaseFields({
    purchase,
    today,
    fundStartDate,
    units,
    buyers,
    ownMemberId = null,
    can,
    errors,
    items,
    setItems,
    totals,
}: Props & {
    items: DraftItem[];
    setItems: (update: (items: DraftItem[]) => DraftItem[]) => void;
    totals: PurchaseTotals | null;
}) {
    const __ = useTranslate();
    const { mess } = usePage().props;
    const { names, suggest } = useItemNameSuggestions();
    const [buyerId, setBuyerId] = useState(
        String(purchase?.buyer_member_id ?? ownMemberId ?? ''),
    );
    const [removeReceipt, setRemoveReceipt] = useState(false);

    const change = (key: number, changes: Partial<DraftItem>) =>
        setItems((current) =>
            current.map((item) =>
                item.key === key ? { ...item, ...changes } : item,
            ),
        );

    return (
        <>
            <Card>
                <fieldset className="flex flex-col gap-4 px-4">
                    <legend className="mb-4 text-subhead">
                        {__('Purchase')}
                    </legend>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field
                            id="purchase_date"
                            label={__('Date bought')}
                            error={errors.purchase_date}
                        >
                            <Input
                                id="purchase_date"
                                name="purchase_date"
                                type="date"
                                defaultValue={purchase?.purchase_date ?? today}
                                max={today}
                                min={fundStartDate ?? undefined}
                                required
                            />
                        </Field>
                        {can.chooseBuyer && (
                            <Field
                                id="buyer_member_id"
                                label={__('Buyer')}
                                error={errors.buyer_member_id}
                            >
                                <input
                                    type="hidden"
                                    name="buyer_member_id"
                                    value={buyerId}
                                />
                                <Select
                                    value={buyerId}
                                    onValueChange={setBuyerId}
                                >
                                    <SelectTrigger
                                        id="buyer_member_id"
                                        className="w-full"
                                    >
                                        <SelectValue
                                            placeholder={__('Choose a member')}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {buyers.map((buyer) => (
                                            <SelectItem
                                                key={buyer.id}
                                                value={String(buyer.id)}
                                            >
                                                {buyer.id === ownMemberId
                                                    ? __(':name (you)', {
                                                          name: buyer.name,
                                                      })
                                                    : buyer.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        )}
                    </div>
                    <Field
                        id="notes"
                        label={__('Notes (optional)')}
                        error={errors.notes}
                    >
                        <Textarea
                            id="notes"
                            name="notes"
                            rows={2}
                            maxLength={2000}
                            defaultValue={purchase?.notes ?? ''}
                        />
                    </Field>
                    <Field
                        id="receipt"
                        label={__('Receipt photo (optional)')}
                        help={__('JPG, PNG, WebP or PDF, up to :size MB.', {
                            size: Math.floor(mess.maxUploadKb / 1024),
                        })}
                        error={errors.receipt}
                    >
                        <Input
                            id="receipt"
                            name="receipt"
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                        />
                    </Field>
                    {purchase?.has_receipt && (
                        <div className="flex items-center gap-3">
                            <input
                                type="hidden"
                                name="remove_receipt"
                                value={removeReceipt ? '1' : '0'}
                            />
                            <Checkbox
                                id="remove_receipt"
                                checked={removeReceipt}
                                onCheckedChange={(checked) =>
                                    setRemoveReceipt(checked === true)
                                }
                            />
                            <Label htmlFor="remove_receipt">
                                {__('Remove the current receipt')}
                            </Label>
                            <a
                                href={receiptRoute.url(purchase.id)}
                                target="_blank"
                                rel="noreferrer"
                                className="text-small text-primary hover:underline"
                            >
                                {__('View it')}
                            </a>
                        </div>
                    )}
                </fieldset>
            </Card>

            <Card>
                <fieldset className="flex flex-col gap-4 px-4">
                    <legend className="mb-4 text-subhead">{__('Items')}</legend>
                    <datalist id="item-name-suggestions">
                        {names.map((name) => (
                            <option key={name} value={name} />
                        ))}
                    </datalist>
                    <ol className="flex flex-col divide-y divide-border">
                        {items.map((item, index) => {
                            const id = (field: string) =>
                                `item-${item.key}-${field}`;
                            const name = (field: string) =>
                                `items[${index}][${field}]`;
                            const error = (field: string) =>
                                errors[`items.${index}.${field}`];
                            const lineTotal = totals?.line_totals[index];

                            return (
                                <li
                                    key={item.key}
                                    className="grid grid-cols-2 gap-3 py-4 first:pt-0 last:pb-0 md:grid-cols-12"
                                >
                                    <Field
                                        id={id('name')}
                                        label={__('Item')}
                                        error={error('item_name')}
                                        className="col-span-2 md:col-span-4"
                                    >
                                        <Input
                                            id={id('name')}
                                            name={name('item_name')}
                                            list="item-name-suggestions"
                                            autoComplete="off"
                                            maxLength={100}
                                            value={item.item_name}
                                            onChange={(event) => {
                                                change(item.key, {
                                                    item_name:
                                                        event.target.value,
                                                });
                                                suggest(event.target.value);
                                            }}
                                            onFocus={() =>
                                                suggest(item.item_name)
                                            }
                                            required
                                        />
                                    </Field>
                                    <Field
                                        id={id('quantity')}
                                        label={__('Quantity')}
                                        error={error('quantity')}
                                        className="md:col-span-2"
                                    >
                                        <Input
                                            id={id('quantity')}
                                            name={name('quantity')}
                                            inputMode="decimal"
                                            pattern="\d+(\.\d{1,3})?"
                                            className="text-right tabular-nums"
                                            value={item.quantity}
                                            onChange={(event) =>
                                                change(item.key, {
                                                    quantity:
                                                        event.target.value,
                                                })
                                            }
                                            required
                                        />
                                    </Field>
                                    <Field
                                        id={id('unit')}
                                        label={__('Unit')}
                                        error={error('unit')}
                                        className="md:col-span-2"
                                    >
                                        <input
                                            type="hidden"
                                            name={name('unit')}
                                            value={item.unit}
                                        />
                                        <Select
                                            value={item.unit}
                                            onValueChange={(unit) =>
                                                change(item.key, {
                                                    unit: unit as GroceryUnitValue,
                                                })
                                            }
                                        >
                                            <SelectTrigger
                                                id={id('unit')}
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {units.map((unit) => (
                                                    <SelectItem
                                                        key={unit.value}
                                                        value={unit.value}
                                                    >
                                                        {unit.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                    <Field
                                        id={id('price')}
                                        label={__('Unit price (৳)')}
                                        error={error('unit_price')}
                                        className="md:col-span-2"
                                    >
                                        <Input
                                            id={id('price')}
                                            name={name('unit_price')}
                                            inputMode="decimal"
                                            pattern="\d+(\.\d{1,2})?"
                                            placeholder="0.00"
                                            className="text-right tabular-nums"
                                            value={item.unit_price}
                                            onChange={(event) =>
                                                change(item.key, {
                                                    unit_price:
                                                        event.target.value,
                                                })
                                            }
                                            required
                                        />
                                    </Field>
                                    <div className="flex items-end justify-between gap-2 md:col-span-2">
                                        <div className="flex flex-col gap-2">
                                            <Label asChild>
                                                <span>{__('Line total')}</span>
                                            </Label>
                                            <span
                                                className="flex h-10 items-center text-ink tabular-nums"
                                                aria-live="polite"
                                            >
                                                {lineTotal
                                                    ? formatMoney(lineTotal)
                                                    : '—'}
                                            </span>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={items.length === 1}
                                            aria-label={__(
                                                'Remove item :number',
                                                { number: index + 1 },
                                            )}
                                            onClick={() =>
                                                setItems((current) =>
                                                    current.filter(
                                                        (other) =>
                                                            other.key !==
                                                            item.key,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden />
                                        </Button>
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                    <InputError message={errors.items} />
                    <Button
                        type="button"
                        variant="outline"
                        className="w-fit"
                        onClick={() =>
                            setItems((current) => [...current, blankItem()])
                        }
                    >
                        <Plus aria-hidden />
                        {__('Add item')}
                    </Button>
                </fieldset>
            </Card>
        </>
    );
}

/**
 * The side panel: the grand total as the server computes it, and what
 * happens when the fund cannot cover it.
 */
export function PurchaseTotalsCard({
    totals,
    pending,
    children,
}: {
    totals: PurchaseTotals | null;
    pending: boolean;
    children?: ReactNode;
}) {
    const __ = useTranslate();
    const { fund } = usePage().props;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-subhead">{__('Total')}</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                <p
                    className="text-heading text-ink tabular-nums"
                    aria-live="polite"
                >
                    {totals ? formatMoney(totals.grand_total) : '—'}
                </p>
                <p className="text-small text-ink-muted">
                    {pending
                        ? __('Working out the total…')
                        : __(
                              'Worked out by the server from the completed lines. It is checked again when you save.',
                          )}
                </p>
                {fund.balance !== null && (
                    <p className="text-small text-ink-muted">
                        {__('The fund holds :amount now.', {
                            amount: formatMoney(fund.balance),
                        })}
                    </p>
                )}
                <p className="text-small text-ink-muted">
                    {__(
                        'The fund pays straight away. If it does not hold enough, the rest is owed to the buyer and repaid later.',
                    )}
                </p>
                {children}
            </CardContent>
        </Card>
    );
}
