import { Combobox } from '@/components/ui/combobox';
import { DatePicker } from '@/components/ui/date-picker';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { SignedMoney } from '@/components/signed-money';
import { StatCard } from '@/components/stat-card';
import { ListTemplate } from '@/components/templates/list-template';
import { StatGrid } from '@/components/templates/page-grid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/ledger';
import { create as createAdjustment } from '@/routes/ledger/adjustments';
import type {
    LedgerDirectionValue,
    LedgerEntryRow,
    LedgerEntryTypeValue,
    LedgerFilters,
    LedgerSummary,
    Option,
    Paginated,
} from '@/types';

type Props = {
    entries: Paginated<LedgerEntryRow>;
    filters: LedgerFilters;
    summary: LedgerSummary;
    types: Option<LedgerEntryTypeValue>[];
    directions: Option<LedgerDirectionValue>[];
    can: { adjust: boolean };
};

const ALL = 'all';

export default function LedgerIndex({
    entries,
    filters,
    summary,
    types,
    directions,
    can,
}: Props) {
    const __ = useTranslate();

    const applyFilters = (changes: Partial<LedgerFilters>) => {
        const next = { ...filters, ...changes };

        router.get(
            index.url({
                query: {
                    from: next.from,
                    to: next.to,
                    type: next.type ?? undefined,
                    direction: next.direction ?? undefined,
                },
            }),
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const details = (entry: LedgerEntryRow) =>
        entry.reverses_entry_id
            ? __('Reverses entry #:id', { id: entry.reverses_entry_id })
            : entry.source_type && entry.source_id
              ? `${entry.source_type.replaceAll('_', ' ')} #${entry.source_id}`
              : null;

    const columns: ResponsiveColumn<LedgerEntryRow>[] = [
        {
            key: 'entry_date',
            header: __('Date'),
            cell: (entry) => formatBusinessDate(entry.entry_date),
        },
        {
            key: 'type',
            header: __('Type'),
            cell: (entry) => <EntryType entry={entry} />,
        },
        {
            key: 'reason',
            header: __('Reason'),
            cell: (entry) => (
                <span className="flex flex-col">
                    <span>{entry.reason ?? '—'}</span>
                    {details(entry) && (
                        <span className="text-small text-ink-muted">
                            {details(entry)}
                        </span>
                    )}
                </span>
            ),
        },
        {
            key: 'created_by',
            header: __('Posted by'),
            cell: (entry) => entry.created_by ?? '—',
            priority: 'low',
        },
        {
            key: 'amount',
            header: __('Amount'),
            align: 'right',
            cell: (entry) => <SignedMoney value={entry.signed_amount} />,
        },
    ];

    return (
        <>
            <Head title={__('Ledger')} />
            <ListTemplate
                header={{
                    title: __('Ledger'),
                    description: __(
                        'Every movement of the shared fund. Entries are never edited; corrections are reversals or adjustments.',
                    ),
                    primaryAction: can.adjust
                        ? {
                              label: __('New adjustment'),
                              href: createAdjustment(),
                              icon: Plus,
                          }
                        : undefined,
                }}
                filters={
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="ledger-from">{__('From')}</Label>
                            <DatePicker
                                id="ledger-from"
                                value={filters.from}
                                max={filters.to}
                                onValueChange={(from) =>
                                    from && applyFilters({ from })
                                }
                                className="w-44"
                            />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="ledger-to">{__('To')}</Label>
                            <DatePicker
                                id="ledger-to"
                                value={filters.to}
                                min={filters.from}
                                onValueChange={(to) =>
                                    to && applyFilters({ to })
                                }
                                className="w-44"
                            />
                        </div>
                    </div>
                }
                moreFilters={
                    <>
                        <FilterSelect
                            id="ledger-type"
                            label={__('Type')}
                            allLabel={__('All types')}
                            value={filters.type}
                            options={types}
                            onChange={(type) => applyFilters({ type })}
                        />
                        <FilterSelect
                            id="ledger-direction"
                            label={__('Direction')}
                            allLabel={__('Credits and debits')}
                            value={filters.direction}
                            options={directions}
                            onChange={(direction) =>
                                applyFilters({ direction })
                            }
                        />
                    </>
                }
                activeFilterCount={
                    (filters.type ? 1 : 0) + (filters.direction ? 1 : 0)
                }
                resultCount={
                    entries.total === 1
                        ? __('1 entry')
                        : __(':count entries', { count: entries.total })
                }
                pagination={
                    entries.last_page > 1 && (
                        <div className="flex items-center gap-3">
                            <PageLink
                                href={entries.prev_page_url}
                                label={__('Previous')}
                            />
                            <span className="text-small text-ink-muted">
                                {__('Page :page of :pages', {
                                    page: entries.current_page,
                                    pages: entries.last_page,
                                })}
                            </span>
                            <PageLink
                                href={entries.next_page_url}
                                label={__('Next')}
                            />
                        </div>
                    )
                }
            >
                <StatGrid
                    stats={[
                        <StatCard
                            key="opening"
                            label={__('Opening balance')}
                            value={formatMoney(summary.opening)}
                            hint={formatBusinessDate(filters.from)}
                        />,
                        <StatCard
                            key="in"
                            label={__('Deposits')}
                            value={formatMoney(summary.deposits)}
                            hint={__('Adjustments :amount', {
                                amount: formatMoney(summary.adjustments, {
                                    signed: true,
                                }),
                            })}
                        />,
                        <StatCard
                            key="out"
                            label={__('Expenses')}
                            value={formatMoney(summary.expenses)}
                            hint={__('Reimbursements :amount', {
                                amount: formatMoney(summary.reimbursements),
                            })}
                        />,
                        <StatCard
                            key="closing"
                            label={__('Closing balance')}
                            value={formatMoney(summary.closing)}
                            hint={formatBusinessDate(filters.to)}
                        />,
                    ]}
                />
                <ResponsiveTable
                    caption={__('Ledger entries')}
                    columns={columns}
                    rows={entries.data}
                    rowKey={(entry) => entry.id}
                    card={(entry) => ({
                        title: entry.reason ?? entry.type_label,
                        value: <SignedMoney value={entry.signed_amount} />,
                        status: <EntryType entry={entry} />,
                        meta: [
                            formatBusinessDate(entry.entry_date),
                            ...(details(entry) ? [details(entry)] : []),
                        ],
                    })}
                    empty={
                        <p className="text-small text-ink-muted">
                            {__('No ledger entries in this period.')}
                        </p>
                    }
                />
            </ListTemplate>
        </>
    );
}

LedgerIndex.layout = {
    breadcrumbs: [{ title: 'Ledger', href: index() }],
};

function EntryType({ entry }: { entry: LedgerEntryRow }) {
    const __ = useTranslate();

    return (
        <span className="flex flex-wrap items-center gap-2">
            <span>{entry.type_label}</span>
            {entry.reversed_by_entry_id && (
                <Badge variant="neutral">{__('Reversed')}</Badge>
            )}
        </span>
    );
}

function FilterSelect<T extends string>({
    id,
    label,
    allLabel,
    value,
    options,
    onChange,
}: {
    id: string;
    label: string;
    allLabel: string;
    value: T | null;
    options: Option<T>[];
    onChange: (value: T | null) => void;
}) {
    const __ = useTranslate();

    return (
        <div className="flex flex-col gap-1 md:flex-row md:items-center md:gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Combobox
                id={id}
                value={value ?? ALL}
                onValueChange={(next) =>
                    onChange(next === ALL ? null : (next as T))
                }
                options={[{ value: ALL, label: allLabel }, ...options]}
                searchPlaceholder={__('Search')}
                emptyText={__('Nothing found.')}
                className="md:w-48"
            />
        </div>
    );
}

function PageLink({ href, label }: { href: string | null; label: string }) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    );
}
