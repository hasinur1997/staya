import { DatePicker } from '@/components/ui/date-picker';
import { MonthPicker } from '@/components/ui/month-picker';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil, Plus, Printer, Receipt, Trash2, Wallet } from 'lucide-react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { StatCard } from '@/components/stat-card';
import { DashboardTemplate } from '@/components/templates/dashboard-template';
import { DetailTemplate } from '@/components/templates/detail-template';
import { FormTemplate } from '@/components/templates/form-template';
import { ListTemplate } from '@/components/templates/list-template';
import { ReportTemplate } from '@/components/templates/report-template';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import { dashboard, layoutPreview } from '@/routes';
import { index as depositsIndex } from '@/routes/deposits';
import { index as groceryIndex } from '@/routes/grocery';
import { index as reportsIndex } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';

// Temporary page for checking docs/layout.md: the shell and each template
// with placeholder content. Delete once real screens use the templates.

type Template = 'dashboard' | 'list' | 'detail' | 'form' | 'report';

type Props = {
    as: string;
    roles: string[];
    template: Template;
    templates: Template[];
};

type Purchase = {
    id: number;
    date: string;
    buyer: string;
    items: number;
    total: string;
    createdBy: string;
    status: 'Locked' | 'Editable';
};

type MemberRow = {
    name: string;
    deposits: string;
    share: string;
    balance: string;
};

const purchases: Purchase[] = [
    {
        id: 41,
        date: '18 Oct 2026',
        buyer: 'Rahim Uddin',
        items: 6,
        total: '1840.00',
        createdBy: 'Rahim Uddin',
        status: 'Editable',
    },
    {
        id: 40,
        date: '17 Oct 2026',
        buyer: 'Karim Hossain',
        items: 4,
        total: '960.50',
        createdBy: 'Manager',
        status: 'Locked',
    },
    {
        id: 39,
        date: '15 Oct 2026',
        buyer: 'Sadia Akter',
        items: 9,
        total: '3215.00',
        createdBy: 'Sadia Akter',
        status: 'Locked',
    },
    {
        id: 38,
        date: '12 Oct 2026',
        buyer: 'Rahim Uddin',
        items: 3,
        total: '450.00',
        createdBy: 'Rahim Uddin',
        status: 'Locked',
    },
];

const members: MemberRow[] = [
    {
        name: 'Rahim Uddin',
        deposits: '3500.00',
        share: '3000.00',
        balance: '500.00',
    },
    {
        name: 'Karim Hossain',
        deposits: '2000.00',
        share: '3000.00',
        balance: '-1000.00',
    },
    {
        name: 'Sadia Akter',
        deposits: '3000.00',
        share: '3000.00',
        balance: '0.00',
    },
];

const lineItems = [
    {
        name: 'Rice (miniket)',
        quantity: '5.000',
        unit: 'kg',
        price: '78.00',
        total: '390.00',
    },
    {
        name: 'Red lentils',
        quantity: '2.000',
        unit: 'kg',
        price: '135.00',
        total: '270.00',
    },
    {
        name: 'Eggs',
        quantity: '2.000',
        unit: 'dozen',
        price: '150.00',
        total: '300.00',
    },
];

const titles: Record<Template, string> = {
    dashboard: 'Dashboard',
    list: 'List',
    detail: 'Detail',
    form: 'Form',
    report: 'Report',
};

const breadcrumbsFor: Record<Template, BreadcrumbItem[]> = {
    dashboard: [{ title: 'Dashboard', href: dashboard() }],
    list: [{ title: 'Grocery purchases', href: groceryIndex() }],
    detail: [
        { title: 'Grocery purchases', href: groceryIndex() },
        { title: 'October', href: groceryIndex() },
        { title: 'Purchase #41', href: groceryIndex() },
    ],
    form: [
        { title: 'Grocery purchases', href: groceryIndex() },
        { title: 'Purchase #41', href: groceryIndex() },
        { title: 'Edit', href: groceryIndex() },
    ],
    report: [
        { title: 'Reports', href: reportsIndex() },
        { title: 'October 2026', href: reportsIndex() },
    ],
};

/** Credit shows + in success, due shows − in error (docs/design-system.md). */
function Balance({ value }: { value: string }) {
    const due = value.startsWith('-');
    const zero = /^-?0+(\.0+)?$/.test(value);

    return (
        <span
            className={cn(
                'tabular-nums',
                !zero && (due ? 'text-error' : 'text-success'),
            )}
        >
            {formatMoney(value, { signed: true })}
            <span className="sr-only">
                {zero ? '' : due ? ' due' : ' credit'}
            </span>
        </span>
    );
}

function StatusBadge({ status }: { status: Purchase['status'] }) {
    return (
        <Badge variant={status === 'Locked' ? 'neutral' : 'success'}>
            {status}
        </Badge>
    );
}

function Placeholder({ children }: { children: ReactNode }) {
    return (
        <p className="rounded-md border border-dashed px-4 py-6 text-center text-caption text-ink-muted">
            {children}
        </p>
    );
}

const purchaseColumns: ResponsiveColumn<Purchase>[] = [
    { key: 'date', header: 'Date', cell: (row) => row.date },
    { key: 'buyer', header: 'Buyer', cell: (row) => row.buyer },
    { key: 'items', header: 'Items', cell: (row) => row.items, align: 'right' },
    {
        key: 'created',
        header: 'Created by',
        cell: (row) => row.createdBy,
        priority: 'low',
    },
    {
        key: 'status',
        header: 'Status',
        cell: (row) => <StatusBadge status={row.status} />,
    },
    {
        key: 'total',
        header: 'Total',
        cell: (row) => formatMoney(row.total),
        align: 'right',
    },
];

const memberColumns: ResponsiveColumn<MemberRow>[] = [
    { key: 'name', header: 'Member', cell: (row) => row.name },
    {
        key: 'deposits',
        header: 'Deposits',
        cell: (row) => formatMoney(row.deposits),
        align: 'right',
    },
    {
        key: 'share',
        header: 'Share',
        cell: (row) => formatMoney(row.share),
        align: 'right',
        priority: 'low',
    },
    {
        key: 'balance',
        header: 'Due / credit',
        cell: (row) => <Balance value={row.balance} />,
        align: 'right',
    },
];

function PurchaseTable() {
    return (
        <ResponsiveTable
            caption="Grocery purchases"
            columns={purchaseColumns}
            rows={purchases}
            rowKey={(row) => row.id}
            rowHref={() => groceryIndex()}
            card={(row) => ({
                title: row.date,
                value: formatMoney(row.total),
                status: <StatusBadge status={row.status} />,
                meta: [row.buyer, `${row.items} items`],
            })}
        />
    );
}

function MemberTable() {
    return (
        <ResponsiveTable
            caption="Per-member due and credit"
            columns={memberColumns}
            rows={members}
            rowKey={(row) => row.name}
            card={(row) => ({
                title: row.name,
                value: <Balance value={row.balance} />,
                meta: [
                    `Deposits ${formatMoney(row.deposits)}`,
                    `Share ${formatMoney(row.share)}`,
                ],
            })}
        />
    );
}

function DashboardPreview() {
    return (
        <DashboardTemplate
            header={{
                title: 'Good evening, Rahim',
                description: 'October 2026',
                primaryAction: {
                    label: 'Add purchase',
                    icon: Plus,
                    href: groceryIndex(),
                },
                secondaryActions: [
                    {
                        label: 'Submit deposit',
                        icon: Wallet,
                        href: depositsIndex(),
                    },
                ],
            }}
            stats={[
                <StatCard
                    key="fund"
                    label="Fund balance"
                    value={formatMoney('12450.00')}
                    hint="Shared mess fund"
                />,
                <StatCard
                    key="dep"
                    label="Your deposits"
                    value={formatMoney('3500.00')}
                    hint="Approved this month"
                />,
                <StatCard
                    key="share"
                    label="Your share"
                    value={formatMoney('3000.00')}
                    hint="8 active members"
                />,
                <StatCard
                    key="bal"
                    label="Due / credit"
                    value={<Balance value="500.00" />}
                    hint="This month only"
                />,
            ]}
            aside={
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Fund owes you</CardTitle>
                            <CardDescription>
                                From purchases larger than the fund
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-money tabular-nums">
                                {formatMoney('0.00')}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent deposits</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div className="flex items-center justify-between gap-3">
                                <Badge variant="warning">Pending</Badge>
                                <span className="tabular-nums">
                                    {formatMoney('1500.00')}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <Badge variant="success">Approved</Badge>
                                <span className="tabular-nums">
                                    {formatMoney('2000.00')}
                                </span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <Badge variant="error">Rejected</Badge>
                                <span className="tabular-nums">
                                    {formatMoney('500.00')}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </>
            }
        >
            <Card>
                <CardHeader>
                    <CardTitle>Recent purchases</CardTitle>
                </CardHeader>
                <CardContent>
                    <PurchaseTable />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Everyone this month</CardTitle>
                </CardHeader>
                <CardContent>
                    <MemberTable />
                </CardContent>
            </Card>
        </DashboardTemplate>
    );
}

function ListPreview() {
    return (
        <ListTemplate
            header={{
                title: 'Grocery purchases',
                description: 'Every purchase paid from the shared fund',
                primaryAction: {
                    label: 'Add purchase',
                    icon: Plus,
                    href: groceryIndex(),
                },
            }}
            filters={
                <div className="flex flex-col gap-1">
                    <Label htmlFor="preview-month" className="sr-only">
                        Month
                    </Label>
                    <MonthPicker
                        id="preview-month"
                        value="2026-10"
                        onValueChange={() => undefined}
                        className="w-48"
                    />
                </div>
            }
            moreFilters={
                <div className="flex flex-col gap-1">
                    <Label htmlFor="preview-buyer">Buyer</Label>
                    <Input
                        id="preview-buyer"
                        placeholder="Any member"
                        className="md:w-48"
                    />
                </div>
            }
            activeFilterCount={1}
            resultCount="4 purchases"
            summary={`Total ${formatMoney('6465.50')} · 4 purchases`}
            pagination={
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="sm" disabled>
                        Previous
                    </Button>
                    <span className="text-caption text-ink-muted">
                        Page 1 of 1
                    </span>
                    <Button variant="outline" size="sm" disabled>
                        Next
                    </Button>
                </div>
            }
        >
            <PurchaseTable />
        </ListTemplate>
    );
}

function DetailPreview() {
    return (
        <DetailTemplate
            header={{
                overline: 'Grocery purchase',
                title: 'Purchase #41',
                status: <Badge variant="success">Editable for 24 min</Badge>,
                description: 'Created by Rahim Uddin · 18 Oct 2026, 7:40 PM',
                primaryAction: {
                    label: 'Edit purchase',
                    icon: Pencil,
                    href: groceryIndex(),
                },
                secondaryActions: [
                    {
                        label: 'View receipt',
                        icon: Receipt,
                        href: groceryIndex(),
                    },
                ],
                menuActions: [
                    {
                        label: 'Void purchase',
                        icon: Trash2,
                        destructive: true,
                        onClick: () => {},
                    },
                ],
            }}
            aside={
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2 text-body">
                            <div className="flex justify-between gap-3">
                                <span className="text-ink-muted">Buyer</span>
                                <span>Rahim Uddin</span>
                            </div>
                            <div className="flex justify-between gap-3">
                                <span className="text-ink-muted">Date</span>
                                <span>18 Oct 2026</span>
                            </div>
                            <div className="flex justify-between gap-3 border-t pt-2">
                                <span className="text-label">Grand total</span>
                                <span className="text-label tabular-nums">
                                    {formatMoney('960.00')}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>History</CardTitle>
                            <CardDescription>
                                Visible with audit.view
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Placeholder>Audit history panel</Placeholder>
                        </CardContent>
                    </Card>
                </>
            }
        >
            <Card>
                <CardHeader>
                    <CardTitle>Items</CardTitle>
                </CardHeader>
                <CardContent>
                    <ResponsiveTable
                        caption="Line items"
                        columns={[
                            {
                                key: 'name',
                                header: 'Item',
                                cell: (row) => row.name,
                            },
                            {
                                key: 'qty',
                                header: 'Quantity',
                                cell: (row) => `${row.quantity} ${row.unit}`,
                                align: 'right',
                            },
                            {
                                key: 'price',
                                header: 'Unit price',
                                cell: (row) => formatMoney(row.price),
                                align: 'right',
                            },
                            {
                                key: 'total',
                                header: 'Line total',
                                cell: (row) => formatMoney(row.total),
                                align: 'right',
                            },
                        ]}
                        rows={lineItems}
                        rowKey={(row) => row.name}
                        card={(row) => ({
                            title: row.name,
                            value: formatMoney(row.total),
                            meta: [
                                `${row.quantity} ${row.unit}`,
                                `${formatMoney(row.price)} each`,
                            ],
                        })}
                    />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Notes</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-body">
                        Bought from Karwan Bazar for the week.
                    </p>
                </CardContent>
            </Card>
        </DetailTemplate>
    );
}

function FormPreview() {
    return (
        <FormTemplate
            header={{
                title: 'Edit purchase',
                description:
                    'Totals are calculated by the server when you save.',
            }}
            formId="preview-form"
            submitLabel="Save purchase"
            cancelHref={groceryIndex()}
            aside={
                <Card>
                    <CardHeader>
                        <CardTitle>Summary</CardTitle>
                        <CardDescription>
                            You can edit this for 24 more minutes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex justify-between gap-3">
                        <span className="text-label">Grand total</span>
                        <span className="text-label tabular-nums">
                            {formatMoney('960.00')}
                        </span>
                    </CardContent>
                </Card>
            }
        >
            <form
                id="preview-form"
                className="flex flex-col gap-4 md:gap-6"
                onSubmit={(event) => event.preventDefault()}
            >
                <Card>
                    <fieldset className="flex flex-col gap-4 px-4">
                        <legend className="mb-4 text-title">Purchase</legend>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="preview-date">
                                    Purchase date
                                </Label>
                                <DatePicker
                                    id="preview-date"
                                    defaultValue="2026-10-18"
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="preview-form-buyer">
                                    Buyer
                                </Label>
                                <Input
                                    id="preview-form-buyer"
                                    defaultValue="Rahim Uddin"
                                />
                            </div>
                        </div>
                    </fieldset>
                </Card>
                <Card>
                    <fieldset className="flex flex-col gap-4 px-4">
                        <legend className="mb-4 text-title">Items</legend>
                        {lineItems.map((item, index) => (
                            <div
                                key={item.name}
                                className="grid grid-cols-2 gap-3 border-b pb-4 last:border-0 md:grid-cols-5 md:items-end"
                            >
                                <div className="col-span-2 flex flex-col gap-2">
                                    <Label htmlFor={`item-${index}`}>
                                        Item
                                    </Label>
                                    <Input
                                        id={`item-${index}`}
                                        defaultValue={item.name}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor={`qty-${index}`}>
                                        Quantity ({item.unit})
                                    </Label>
                                    <Input
                                        id={`qty-${index}`}
                                        inputMode="decimal"
                                        defaultValue={item.quantity}
                                    />
                                </div>
                                <div className="flex flex-col gap-2">
                                    <Label htmlFor={`price-${index}`}>
                                        Unit price
                                    </Label>
                                    <Input
                                        id={`price-${index}`}
                                        inputMode="decimal"
                                        defaultValue={item.price}
                                    />
                                </div>
                                <p className="col-span-2 text-right text-label tabular-nums md:col-span-1 md:pb-2">
                                    {formatMoney(item.total)}
                                </p>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full md:w-auto md:self-start"
                        >
                            <Plus aria-hidden />
                            Add item
                        </Button>
                    </fieldset>
                </Card>
            </form>
        </FormTemplate>
    );
}

function ReportPreview() {
    return (
        <ReportTemplate
            header={{
                overline: 'Monthly report',
                title: 'October 2026',
                status: <Badge variant="neutral">Open</Badge>,
                description: '8 active members · times shown in Asia/Dhaka',
                primaryAction: { label: 'Close month', onClick: () => {} },
                secondaryActions: [
                    {
                        label: 'Print',
                        icon: Printer,
                        onClick: () => window.print(),
                    },
                ],
            }}
            period={
                <>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="report-month" className="sr-only">
                            Month
                        </Label>
                        <MonthPicker
                            id="report-month"
                            value="2026-10"
                            onValueChange={() => undefined}
                            className="w-full md:w-48"
                        />
                    </div>
                    <Button variant="outline">Custom range</Button>
                </>
            }
            summary={[
                <StatCard
                    key="open"
                    label="Opening balance"
                    value={formatMoney('9500.00')}
                />,
                <StatCard
                    key="dep"
                    label="Deposits"
                    value={formatMoney('26950.00')}
                    hint="Approved"
                />,
                <StatCard
                    key="exp"
                    label="Expenses"
                    value={formatMoney('24000.00')}
                />,
                <StatCard
                    key="close"
                    label="Closing balance"
                    value={formatMoney('12450.00')}
                />,
                <StatCard key="members" label="Active members" value="8" />,
                <StatCard
                    key="share"
                    label="Share per member"
                    value={formatMoney('3000.00')}
                />,
                <StatCard
                    key="adj"
                    label="Adjustments"
                    value={formatMoney('0.00')}
                />,
                <StatCard
                    key="reimb"
                    label="Reimbursements"
                    value={formatMoney('0.00')}
                />,
            ]}
        >
            <Card>
                <CardHeader>
                    <CardTitle>Per member</CardTitle>
                    <CardDescription>
                        Approved deposits minus the equal share
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <MemberTable />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Purchases</CardTitle>
                </CardHeader>
                <CardContent>
                    <PurchaseTable />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Pending and rejected deposits</CardTitle>
                </CardHeader>
                <CardContent>
                    <Placeholder>Deposit summary</Placeholder>
                </CardContent>
            </Card>
        </ReportTemplate>
    );
}

function Switcher({
    label,
    options,
    current,
    hrefFor,
}: {
    label: string;
    options: string[];
    current: string;
    hrefFor: (option: string) => string;
}) {
    return (
        <div
            className="flex flex-wrap items-center gap-2"
            role="group"
            aria-label={label}
        >
            <span className="text-caption text-ink-muted">{label}</span>
            {options.map((option) => (
                <Button
                    key={option}
                    asChild
                    size="sm"
                    variant={option === current ? 'outline' : 'ghost'}
                >
                    <Link
                        href={hrefFor(option)}
                        aria-current={option === current ? 'page' : undefined}
                        preserveScroll
                        className={cn(
                            option === current && 'bg-surface-raised',
                        )}
                    >
                        {option.charAt(0).toUpperCase() + option.slice(1)}
                    </Link>
                </Button>
            ))}
        </div>
    );
}

export default function LayoutPreview({
    as,
    roles,
    template,
    templates,
}: Props) {
    useEffect(() => {
        setLayoutProps({ breadcrumbs: breadcrumbsFor[template] });
    }, [template]);

    const href = (query: { as?: string; template?: string }) =>
        layoutPreview.url({ query: { as, template, ...query } });

    return (
        <>
            <Head title={`Layout preview: ${titles[template]}`} />

            <div className="mb-6 flex flex-col gap-3 rounded-lg border border-dashed p-4 print:hidden">
                <p className="text-overline text-neutral">
                    Layout preview (local only)
                </p>
                <Switcher
                    label="Navigation as"
                    options={roles}
                    current={as}
                    hrefFor={(option) => href({ as: option })}
                />
                <Switcher
                    label="Template"
                    options={templates}
                    current={template}
                    hrefFor={(option) => href({ template: option })}
                />
            </div>

            {template === 'dashboard' && <DashboardPreview />}
            {template === 'list' && <ListPreview />}
            {template === 'detail' && <DetailPreview />}
            {template === 'form' && <FormPreview />}
            {template === 'report' && <ReportPreview />}
        </>
    );
}

LayoutPreview.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
