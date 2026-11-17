import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { show } from '@/routes/grocery';
import type { PurchaseRow } from '@/types';

/** The latest purchases the member made, newest first. */
export function MemberPurchasesCard({
    purchases,
}: {
    purchases: PurchaseRow[];
}) {
    const __ = useTranslate();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-title">
                    {__('Purchases made')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {purchases.length === 0 ? (
                    <p className="text-caption text-ink-muted">
                        {__('No purchases yet.')}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y divide-border">
                        {purchases.map((purchase) => (
                            <li
                                key={purchase.id}
                                className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                            >
                                <span className="flex min-w-0 flex-col gap-1">
                                    <Link
                                        href={show(purchase.id)}
                                        className="text-ink hover:text-primary"
                                    >
                                        {formatBusinessDate(
                                            purchase.purchase_date,
                                        )}
                                    </Link>
                                    <span className="truncate text-caption text-ink-muted">
                                        {purchase.item_names.join(', ')}
                                    </span>
                                </span>
                                <span className="tabular-nums">
                                    {formatMoney(purchase.grand_total)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
