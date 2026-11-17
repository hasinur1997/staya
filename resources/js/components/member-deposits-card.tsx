import { Link } from '@inertiajs/react';
import {
    DepositStatusBadge,
    depositNote,
} from '@/components/deposit-status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { show } from '@/routes/deposits';
import type { DepositRow } from '@/types';

/** The member's latest deposits on their profile, newest first. */
export function MemberDepositsCard({ deposits }: { deposits: DepositRow[] }) {
    const __ = useTranslate();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-subhead">{__('Deposits')}</CardTitle>
            </CardHeader>
            <CardContent>
                {deposits.length === 0 ? (
                    <p className="text-small text-ink-muted">
                        {__('No deposits yet.')}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y divide-border">
                        {deposits.map((deposit) => (
                            <li
                                key={deposit.id}
                                className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                            >
                                <span className="flex flex-col gap-1">
                                    <Link
                                        href={show(deposit.id)}
                                        className="text-ink hover:text-primary"
                                    >
                                        {formatBusinessDate(
                                            deposit.deposit_date,
                                        )}
                                    </Link>
                                    <DepositStatusBadge
                                        status={deposit.status}
                                        label={deposit.status_label}
                                        selfApproved={deposit.is_self_approved}
                                    />
                                    {depositNote(deposit) && (
                                        <span className="text-small text-ink-muted">
                                            {depositNote(deposit)}
                                        </span>
                                    )}
                                </span>
                                <span className="tabular-nums">
                                    {formatMoney(deposit.amount)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
