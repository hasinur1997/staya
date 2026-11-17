import { SignedMoney } from '@/components/signed-money';
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
import type { MemberMonth } from '@/types';

/** Deposits, equal share and due or credit for each of the last months. */
export function MemberMonthlyCard({ months }: { months: MemberMonth[] }) {
    const __ = useTranslate();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-subhead">
                    {__('Monthly due or credit')}
                </CardTitle>
                <p className="text-small text-ink-muted">
                    {__('Each month stands alone. Nothing carries forward.')}
                </p>
            </CardHeader>
            <CardContent>
                {months.length === 0 ? (
                    <p className="text-small text-ink-muted">
                        {__('No months to show yet.')}
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{__('Month')}</TableHead>
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
                            {months.map((row) => (
                                <TableRow key={row.month}>
                                    <TableCell>
                                        {formatMonth(row.month)}
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
    );
}
