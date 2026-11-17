import { useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { itemNames, totals as totalsRoute } from '@/routes/grocery';
import type { GroceryUnitValue, PurchaseTotals } from '@/types';

export type DraftItem = {
    /** Stable React key; never sent to the server. */
    key: number;
    item_name: string;
    quantity: string;
    unit: GroceryUnitValue;
    unit_price: string;
};

const DEBOUNCE_MS = 300;

/**
 * Asks the server to price the items typed so far. The form never does
 * money arithmetic: it shows these figures, or nothing while they load.
 */
export function usePurchaseTotals(items: DraftItem[]): {
    totals: PurchaseTotals | null;
    pending: boolean;
} {
    const http = useHttp<
        { items: { quantity: string; unit_price: string }[] },
        PurchaseTotals
    >({ items: [] });
    const [result, setResult] = useState<{
        signature: string;
        totals: PurchaseTotals;
    } | null>(null);
    const latest = useRef('');
    const signature = JSON.stringify(
        items.map((item) => [item.quantity, item.unit_price]),
    );

    useEffect(() => {
        latest.current = signature;

        const timer = window.setTimeout(() => {
            const lines: [string, string][] = JSON.parse(signature);

            http.transform(() => ({
                items: lines.map(([quantity, unit_price]) => ({
                    quantity,
                    unit_price,
                })),
            }));
            http.post(totalsRoute.url())
                .then((totals) => {
                    if (latest.current === signature) {
                        setResult({ signature, totals });
                    }
                })
                .catch(() => undefined);
        }, DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
        // The http helper is recreated each render; the lines are what matter.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [signature]);

    const current = result?.signature === signature;

    return { totals: current ? result.totals : null, pending: !current };
}

/**
 * Item names used in earlier purchases that start with what was typed.
 */
export function useItemNameSuggestions(): {
    names: string[];
    suggest: (typed: string) => void;
} {
    const http = useHttp<{ q: string }, { names: string[] }>({ q: '' });
    const [names, setNames] = useState<string[]>([]);
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const suggest = (typed: string) => {
        window.clearTimeout(timer.current);

        timer.current = window.setTimeout(() => {
            http.transform(() => ({ q: typed.trim() }));
            http.get(itemNames.url())
                .then((response) => setNames(response.names))
                .catch(() => undefined);
        }, DEBOUNCE_MS);
    };

    return { names, suggest };
}
