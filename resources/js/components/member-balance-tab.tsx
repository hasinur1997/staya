import { SignedMoney } from '@/components/signed-money';
import { StatCard } from '@/components/stat-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMonth } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import type { MemberBalance } from '@/types';

/** What the member has to pay the mess and what the fund owes them. */
export function MemberBalanceTab({ balance }: { balance: MemberBalance }) {
    const __ = useTranslate();

    return (
        <div className="flex flex-col gap-4 md:gap-6">
            <div className="grid gap-4 md:grid-cols-3">
                <StatCard
                    label={__('To pay the mess')}
                    value={formatMoney(balance.to_pay)}
                    hint={__(
                        'Months where their share was more than their deposits.',
                    )}
                />
                <StatCard
                    label={__('To take from the fund')}
                    value={formatMoney(balance.fund_owes)}
                    hint={__(
                        'Purchases they paid for that the fund has not repaid.',
                    )}
                />
                <StatCard
                    label={__('Extra deposited')}
                    value={formatMoney(balance.credit)}
                    hint={__(
                        'Months where deposits were more than their share.',
                    )}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-title">
                        {__('Month by month')}
                    </CardTitle>
                    <p className="text-caption text-ink-muted">
                        {__(
                            'Each month stands alone. Nothing carries forward.',
                        )}
                    </p>
                </CardHeader>
                <CardContent>
                    {balance.months.length === 0 ? (
                        <p className="text-caption text-ink-muted">
                            {__('No months to show yet.')}
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{__('Month')}</TableHead>
                                    <TableHead className="text-right">
                                        {__('Meals')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {__('Deposits')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {__('Share')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {__('Due or credit')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {balance.months.map((row) => (
                                    <TableRow key={row.month}>
                                        <TableCell>
                                            {formatMonth(row.month)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.meals}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(row.deposits)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(row.share)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <SignedMoney
                                                value={row.due_credit}
                                                positiveLabel={__('credit')}
                                                negativeLabel={__('due')}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
