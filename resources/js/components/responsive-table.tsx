import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export type ResponsiveColumn<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    /** Money and counts align right. */
    align?: 'left' | 'right';
    /** Low-priority columns are hidden on tablet. */
    priority?: 'low';
};

export type ResponsiveCard = {
    title: ReactNode;
    /** Usually money, shown right-aligned on the first line. */
    value?: ReactNode;
    status?: ReactNode;
    meta?: ReactNode[];
};

type Props<T> = {
    caption: string;
    columns: ResponsiveColumn<T>[];
    rows: T[];
    rowKey: (row: T) => string | number;
    /** How a row reads as a card on mobile. */
    card: (row: T) => ResponsiveCard;
    rowHref?: (row: T) => NonNullable<InertiaLinkProps['href']>;
    empty?: ReactNode;
};

/**
 * One column definition, two renderings: a table from tablet up (and in
 * print), a list of cards on mobile (docs/layout.md, collapse rule 3).
 */
export function ResponsiveTable<T>({
    caption,
    columns,
    rows,
    rowKey,
    card,
    rowHref,
    empty,
}: Props<T>) {
    if (rows.length === 0 && empty) {
        return <>{empty}</>;
    }

    const lowPriority = 'hidden lg:table-cell print:table-cell';

    return (
        <>
            <div className="hidden md:block print:block">
                <Table>
                    <TableCaption className="sr-only">{caption}</TableCaption>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    align={column.align}
                                    className={cn(
                                        column.priority === 'low' &&
                                            lowPriority,
                                    )}
                                >
                                    {column.header}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {rows.map((row) => (
                            <TableRow key={rowKey(row)} className="relative">
                                {columns.map((column, index) => (
                                    <TableCell
                                        key={column.key}
                                        align={column.align}
                                        className={cn(
                                            column.priority === 'low' &&
                                                lowPriority,
                                        )}
                                    >
                                        {index === 0 && rowHref ? (
                                            // The link covers the whole row.
                                            <Link
                                                href={rowHref(row)}
                                                className="text-ink after:absolute after:inset-0 hover:text-primary"
                                            >
                                                {column.cell(row)}
                                            </Link>
                                        ) : (
                                            column.cell(row)
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <ul
                className="flex flex-col gap-3 md:hidden print:hidden"
                aria-label={caption}
            >
                {rows.map((row) => {
                    const { title, value, status, meta = [] } = card(row);
                    const body = (
                        <Card className="min-h-11 gap-1 px-4 transition-shadow duration-150 hover:shadow-card">
                            <div className="flex items-baseline justify-between gap-3">
                                <span className="min-w-0 truncate text-label text-ink">
                                    {title}
                                </span>
                                {value && (
                                    <span className="shrink-0 text-right text-label text-ink tabular-nums">
                                        {value}
                                    </span>
                                )}
                            </div>
                            {(status || meta.length > 0) && (
                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-small text-ink-muted">
                                    {status}
                                    {meta.map((item, index) => (
                                        <span key={index}>
                                            {index > 0 && (
                                                <span aria-hidden>· </span>
                                            )}
                                            {item}
                                        </span>
                                    ))}
                                </div>
                            )}
                        </Card>
                    );

                    return (
                        <li key={rowKey(row)}>
                            {rowHref ? (
                                <Link
                                    href={rowHref(row)}
                                    className="block rounded-lg"
                                >
                                    {body}
                                </Link>
                            ) : (
                                body
                            )}
                        </li>
                    );
                })}
            </ul>
        </>
    );
}
