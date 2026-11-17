import { Link } from '@inertiajs/react';
import {
    DepositStatusBadge,
    depositNote,
} from '@/components/deposit-status-badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
                <CardTitle className="text-title">{__('Deposits')}</CardTitle>
                <p className="text-caption text-ink-muted">
                    {__('The latest 20 deposits, newest first.')}
                </p>
            </CardHeader>
            <CardContent>
                {deposits.length === 0 ? (
                    <p className="text-caption text-ink-muted">
                        {__('No deposits yet.')}
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{__('Date')}</TableHead>
                                <TableHead>{__('Status')}</TableHead>
                                <TableHead className="text-right">
                                    {__('Amount')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {deposits.map((deposit) => (
                                <TableRow key={deposit.id}>
                                    <TableCell className="whitespace-nowrap">
                                        <Link
                                            href={show(deposit.id)}
                                            className="text-primary hover:underline"
                                        >
                                            {formatBusinessDate(
                                                deposit.deposit_date,
                                            )}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col items-start gap-1">
                                            <DepositStatusBadge
                                                status={deposit.status}
                                                label={deposit.status_label}
                                                selfApproved={
                                                    deposit.is_self_approved
                                                }
                                            />
                                            {depositNote(deposit) && (
                                                <span className="text-caption text-ink-muted">
                                                    {depositNote(deposit)}
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {formatMoney(deposit.amount)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
