import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { index as ledger } from '@/routes/ledger';

// Temporary page for checking docs/design-system.md visually. Delete once the
// design system has been signed off.

const colours = [
    'background',
    'surface',
    'surface-raised',
    'border',
    'ink',
    'ink-muted',
    'neutral',
    'primary',
    'primary-hover',
    'on-primary',
    'highlight',
    'success',
    'warning',
    'error',
    'focus-ring',
] as const;

const typeStyles = [
    { name: 'display', className: 'text-display', sample: 'Shared fund' },
    { name: 'money-lg', className: 'text-money-lg', sample: '৳ 12,450.00' },
    { name: 'heading', className: 'text-heading', sample: 'October deposits' },
    { name: 'title', className: 'text-title', sample: 'Grocery purchase' },
    { name: 'money', className: 'text-money', sample: '৳ 12,450.00' },
    {
        name: 'body',
        className: 'text-body',
        sample: 'Rice, lentils and onions for the week. বাজারের হিসাব',
    },
    {
        name: 'body-strong',
        className: 'text-body-strong',
        sample: 'Rahim Uddin',
    },
    { name: 'label', className: 'text-label', sample: 'Approve deposit' },
    {
        name: 'caption',
        className: 'text-caption',
        sample: '17 Oct 2026, 9:40 PM',
    },
    {
        name: 'overline',
        className: 'text-overline uppercase',
        sample: 'Manage',
    },
] as const;

const spacing = [
    'space-1',
    'space-2',
    'space-3',
    'space-4',
    'space-5',
    'space-6',
    'space-8',
    'space-10',
    'space-12',
    'space-16',
    'space-20',
] as const;

const radii = [
    { name: 'radius-sm', className: 'rounded-sm' },
    { name: 'radius-md', className: 'rounded-md' },
    { name: 'radius-lg', className: 'rounded-lg' },
    { name: 'radius-pill', className: 'rounded-pill' },
] as const;

const tokenNames = [
    ...colours,
    ...spacing,
    ...radii.map((radius) => radius.name),
];

const amounts = ['৳ 12,450.00', '৳ 1,111.11', '৳ 980.50', '৳ 100,000.00'];

function useTokenValues(names: readonly string[]) {
    const ref = useRef<HTMLDivElement>(null);
    const [values, setValues] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!ref.current) {
            return;
        }

        const style = getComputedStyle(ref.current);

        setValues(
            Object.fromEntries(
                names.map((name) => [
                    name,
                    style.getPropertyValue(`--${name}`).trim(),
                ]),
            ),
        );
    }, [names]);

    return { ref, values };
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-heading">{title}</h2>
            {children}
        </section>
    );
}

function ThemePanel({ theme }: { theme: 'light' | 'dark' }) {
    const { ref, values } = useTokenValues(tokenNames);

    return (
        <div
            ref={ref}
            data-theme={theme}
            className="flex flex-1 flex-col gap-8 bg-background p-4 text-ink md:p-8"
        >
            <h1 className="text-display capitalize">{theme}</h1>

            <Section title="Colour">
                <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
                    {colours.map((name) => (
                        <div
                            key={name}
                            className="flex flex-col overflow-hidden rounded-md border border-border bg-surface"
                        >
                            <div
                                className="h-16 border-b border-border"
                                style={{ backgroundColor: `var(--${name})` }}
                            />
                            <div className="flex flex-col p-2">
                                <span className="text-label">{name}</span>
                                <span className="text-caption text-ink-muted">
                                    {values[name]}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </Section>

            <Section title="Type">
                <div className="flex flex-col gap-4 rounded-lg border border-border bg-surface p-4 shadow-card">
                    {typeStyles.map((style) => (
                        <div key={style.name} className="flex flex-col gap-1">
                            <span className="text-caption text-ink-muted">
                                {style.name}
                            </span>
                            <span className={style.className}>
                                {style.sample}
                            </span>
                        </div>
                    ))}
                </div>
                <div className="flex flex-col gap-1 rounded-lg border border-border bg-surface p-4 shadow-card">
                    <span className="text-label text-ink-muted">
                        money: tabular figures, right-aligned
                    </span>
                    {amounts.map((amount) => (
                        <span key={amount} className="text-right text-money">
                            {amount}
                        </span>
                    ))}
                    <span className="text-right text-money text-success">
                        + ৳ 500.00
                    </span>
                    <span className="text-right text-money text-error">
                        − ৳ 1,000.00
                    </span>
                    <span className="text-right text-money text-ink-muted">
                        ৳ 0.00
                    </span>
                </div>
            </Section>

            <Section title="Spacing">
                <div className="flex flex-col gap-2">
                    {spacing.map((name) => (
                        <div key={name} className="flex items-center gap-4">
                            <span className="w-24 text-label">{name}</span>
                            <div
                                className="h-4 bg-primary"
                                style={{ width: `var(--${name})` }}
                            />
                            <span className="text-caption text-ink-muted">
                                {values[name]}
                            </span>
                        </div>
                    ))}
                </div>
            </Section>

            <Section title="Radius and shadow">
                <div className="flex flex-wrap gap-6">
                    {radii.map((radius) => (
                        <div key={radius.name} className="flex flex-col gap-2">
                            <div
                                className={`h-16 w-32 border border-border bg-primary-soft ${radius.className}`}
                            />
                            <span className="text-label">{radius.name}</span>
                            <span className="text-caption text-ink-muted">
                                {values[radius.name]}
                            </span>
                        </div>
                    ))}
                </div>
                <div className="rounded-lg border border-border bg-surface p-4 shadow-card">
                    <p className="text-title">shadow-card</p>
                    <p className="text-body text-ink-muted">
                        A card in its hover state.
                    </p>
                </div>
            </Section>

            <Section title="Focus ring">
                <p className="text-body text-ink-muted">
                    Press Tab to move through these controls.
                </p>
                <div className="flex flex-wrap items-center gap-4">
                    <button
                        type="button"
                        className="rounded-md bg-primary px-4 py-2 text-label text-on-primary"
                    >
                        Add grocery purchase
                    </button>
                    <input
                        type="text"
                        placeholder="Amount"
                        className="rounded-md border border-border bg-surface px-4 py-2 text-body outline-none"
                    />
                    <a
                        href={ledger().url}
                        className="text-label text-primary underline"
                    >
                        View ledger
                    </a>
                </div>
            </Section>
        </div>
    );
}

export default function DesignPreview() {
    return (
        <>
            <Head title="Design preview" />
            <div className="flex min-h-screen flex-col lg:flex-row">
                <ThemePanel theme="light" />
                <ThemePanel theme="dark" />
            </div>
        </>
    );
}
