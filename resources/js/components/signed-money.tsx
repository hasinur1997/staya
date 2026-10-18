import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

/**
 * A signed decimal string: credit shows + in success, debit or due shows −
 * in error (docs/design-system.md). The word is read out for screen readers.
 */
export function SignedMoney({
    value,
    positiveLabel = 'credit',
    negativeLabel = 'debit',
}: {
    value: string;
    positiveLabel?: string;
    negativeLabel?: string;
}) {
    const negative = value.trim().startsWith('-');
    const zero = /^[-+]?0*(\.0*)?$/.test(value.trim());

    return (
        <span
            className={cn(
                'tabular-nums',
                !zero && (negative ? 'text-error' : 'text-success'),
            )}
        >
            {formatMoney(value, { signed: true })}
            {!zero && (
                <span className="sr-only">
                    {' '}
                    {negative ? negativeLabel : positiveLabel}
                </span>
            )}
        </span>
    );
}
