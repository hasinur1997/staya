import { useLayoutEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/*
 * Small SVG charts, drawn from the design tokens (no chart library). The
 * server sends every money figure as a decimal string; the numbers made from
 * them here only decide how tall a bar is. They are never shown or summed,
 * the text beside each mark is the server's own figure.
 */

const WIDTH = 600;
const HEIGHT = 240;
const PAD = { top: 12, right: 12, bottom: 28, left: 52 };

const plot = { h: HEIGHT - PAD.top - PAD.bottom };

/**
 * The width of the chart's box. Charts are drawn at that width instead of
 * being scaled, so axis text stays the same size on a phone.
 */
function useChartWidth(): [(node: HTMLDivElement | null) => void, number] {
    const [width, setWidth] = useState(WIDTH);
    const [node, setNode] = useState<HTMLDivElement | null>(null);

    useLayoutEffect(() => {
        if (!node) {
            return;
        }

        const update = () =>
            setWidth(
                Math.max(240, Math.round(node.getBoundingClientRect().width)),
            );

        update();
        const observer = new ResizeObserver(update);
        observer.observe(node);

        return () => observer.disconnect();
    }, [node]);

    return [setNode, width];
}

const size = (value: string | number) => Number(value);

/** 62710 becomes "62.7k", for axis labels only. */
function compact(value: number): string {
    const abs = Math.abs(value);
    const sign = value < 0 ? '−' : '';

    if (abs >= 1_000_000) {
        return `${sign}${(abs / 1_000_000).toFixed(1).replace(/\.0$/, '')}M`;
    }

    if (abs >= 1_000) {
        return `${sign}${(abs / 1_000).toFixed(1).replace(/\.0$/, '')}k`;
    }

    return `${sign}${Math.round(abs)}`;
}

/** A round upper bound, so the grid lines fall on tidy numbers. */
function niceCeil(value: number): number {
    if (value <= 0) {
        return 1;
    }

    const power = 10 ** Math.floor(Math.log10(value));
    const fraction = value / power;
    const step = fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10;

    return step * power;
}

export function ChartCard({
    title,
    description,
    legend,
    children,
    className,
}: {
    title: string;
    description?: string;
    legend?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <Card className={cn('h-full', className)}>
            <CardHeader className="flex-col items-start gap-2 sm:flex-row sm:justify-between sm:gap-4">
                <div className="flex flex-col gap-1">
                    <CardTitle>{title}</CardTitle>
                    {description && (
                        <p className="text-caption text-ink-muted">
                            {description}
                        </p>
                    )}
                </div>
                {legend}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export function Legend({
    items,
}: {
    items: { label: string; className: string }[];
}) {
    return (
        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-caption text-ink-muted sm:justify-end">
            {items.map((item) => (
                <li key={item.label} className="flex items-center gap-2">
                    <span
                        aria-hidden
                        className={cn('size-2.5 rounded-sm', item.className)}
                    />
                    {item.label}
                </li>
            ))}
        </ul>
    );
}

export function EmptyChart({ children }: { children: ReactNode }) {
    return (
        <p className="flex h-40 items-center justify-center text-caption text-ink-muted">
            {children}
        </p>
    );
}

function Grid({
    max,
    min = 0,
    width,
}: {
    max: number;
    min?: number;
    width: number;
}) {
    const ticks = 4;
    const span = max - min;

    return (
        <>
            {Array.from({ length: ticks + 1 }, (_, index) => {
                const value = min + (span * index) / ticks;
                const y = PAD.top + plot.h - (plot.h * index) / ticks;

                return (
                    <g key={index}>
                        <line
                            x1={PAD.left}
                            x2={width - PAD.right}
                            y1={y}
                            y2={y}
                            className="stroke-border"
                            strokeDasharray={index === 0 ? undefined : '3 4'}
                        />
                        <text
                            x={PAD.left - 8}
                            y={y + 4}
                            textAnchor="end"
                            className="fill-ink-muted text-caption tabular-nums"
                        >
                            {compact(value)}
                        </text>
                    </g>
                );
            })}
        </>
    );
}

type Pair = {
    label: string;
    a: string;
    b: string;
    aText: string;
    bText: string;
};

/** Two bars per month, side by side. */
export function GroupedBars({
    data,
    aLabel,
    bLabel,
}: {
    data: Pair[];
    aLabel: string;
    bLabel: string;
}) {
    const [ref, width] = useChartWidth();
    const plotW = width - PAD.left - PAD.right;
    const max = niceCeil(
        Math.max(...data.flatMap((d) => [size(d.a), size(d.b)])),
    );
    const slot = plotW / data.length;
    const bar = Math.min(28, slot / 3);
    const y = (value: number) => PAD.top + plot.h - (plot.h * value) / max;

    return (
        <div ref={ref}>
            <svg
                width={width}
                height={HEIGHT}
                viewBox={`0 0 ${width} ${HEIGHT}`}
                role="img"
                aria-label={`${aLabel} and ${bLabel}`}
                className="block"
            >
                <Grid max={max} width={width} />
                {data.map((d, index) => {
                    const center = PAD.left + slot * index + slot / 2;

                    return (
                        <g key={d.label}>
                            <rect
                                x={center - bar - 2}
                                y={y(size(d.a))}
                                width={bar}
                                height={PAD.top + plot.h - y(size(d.a))}
                                rx={4}
                                className="fill-primary"
                            >
                                <title>{`${d.label}: ${aLabel} ${d.aText}`}</title>
                            </rect>
                            <rect
                                x={center + 2}
                                y={y(size(d.b))}
                                width={bar}
                                height={PAD.top + plot.h - y(size(d.b))}
                                rx={4}
                                className="fill-highlight"
                            >
                                <title>{`${d.label}: ${bLabel} ${d.bText}`}</title>
                            </rect>
                            <text
                                x={center}
                                y={HEIGHT - 8}
                                textAnchor="middle"
                                className="fill-ink-muted text-caption tabular-nums"
                            >
                                {d.label}
                            </text>
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

type Point = { label: string; value: string; text: string };

/** A line with a soft area under it; the balance can dip below zero. */
export function AreaLine({ data, name }: { data: Point[]; name: string }) {
    const [ref, width] = useChartWidth();
    const plotW = width - PAD.left - PAD.right;
    const values = data.map((d) => size(d.value));
    const max = niceCeil(Math.max(0, ...values));
    const min =
        Math.min(0, ...values) < 0 ? -niceCeil(-Math.min(...values)) : 0;
    const span = max - min;
    const x = (index: number) =>
        PAD.left +
        (data.length === 1 ? plotW / 2 : (plotW * index) / (data.length - 1));
    const y = (value: number) =>
        PAD.top + plot.h - (plot.h * (value - min)) / span;

    const line = data
        .map(
            (d, index) =>
                `${index === 0 ? 'M' : 'L'}${x(index)},${y(size(d.value))}`,
        )
        .join(' ');
    const area = `${line} L${x(data.length - 1)},${y(Math.max(min, 0))} L${x(0)},${y(Math.max(min, 0))} Z`;

    return (
        <div ref={ref}>
            <svg
                width={width}
                height={HEIGHT}
                viewBox={`0 0 ${width} ${HEIGHT}`}
                role="img"
                aria-label={name}
                className="block"
            >
                <Grid max={max} min={min} width={width} />
                <path d={area} className="fill-primary/10" />
                <path
                    d={line}
                    fill="none"
                    strokeWidth={2.5}
                    strokeLinejoin="round"
                    strokeLinecap="round"
                    className="stroke-primary"
                />
                {data.map((d, index) => (
                    <g key={d.label}>
                        <circle
                            cx={x(index)}
                            cy={y(size(d.value))}
                            r={4}
                            className="fill-background stroke-primary"
                            strokeWidth={2}
                        >
                            <title>{`${d.label}: ${d.text}`}</title>
                        </circle>
                        <text
                            x={x(index)}
                            y={HEIGHT - 8}
                            textAnchor="middle"
                            className="fill-ink-muted text-caption tabular-nums"
                        >
                            {d.label}
                        </text>
                    </g>
                ))}
            </svg>
        </div>
    );
}

/** One column per day; days with nothing stay empty. */
export function Columns({
    data,
    name,
    tone = 'primary',
    whole = false,
}: {
    data: Point[];
    name: string;
    tone?: 'primary' | 'highlight';
    /** Counts, so the grid lines fall on whole numbers. */
    whole?: boolean;
}) {
    const [ref, width] = useChartWidth();
    const plotW = width - PAD.left - PAD.right;
    const top = Math.max(...data.map((d) => size(d.value)));
    const max = whole ? Math.max(4, Math.ceil(top / 4) * 4) : niceCeil(top);
    const slot = plotW / data.length;
    const bar = Math.max(3, slot * 0.62);
    const y = (value: number) => PAD.top + plot.h - (plot.h * value) / max;
    const every = data.length > 20 ? 5 : 2;

    return (
        <div ref={ref}>
            <svg
                width={width}
                height={HEIGHT}
                viewBox={`0 0 ${width} ${HEIGHT}`}
                role="img"
                aria-label={name}
                className="block"
            >
                <Grid max={max} width={width} />
                {data.map((d, index) => {
                    const center = PAD.left + slot * index + slot / 2;
                    const value = size(d.value);

                    return (
                        <g key={d.label}>
                            {value > 0 && (
                                <rect
                                    x={center - bar / 2}
                                    y={y(value)}
                                    width={bar}
                                    height={PAD.top + plot.h - y(value)}
                                    rx={2}
                                    className={
                                        tone === 'primary'
                                            ? 'fill-primary'
                                            : 'fill-highlight'
                                    }
                                >
                                    <title>{`${d.label}: ${d.text}`}</title>
                                </rect>
                            )}
                            {(index === 0 || (index + 1) % every === 0) && (
                                <text
                                    x={center}
                                    y={HEIGHT - 8}
                                    textAnchor="middle"
                                    className="fill-ink-muted text-caption tabular-nums"
                                >
                                    {d.label}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

type Row = { label: string; value: string; text: ReactNode };

/** Ranked horizontal bars, with the figure at the end of each. */
export function RankedBars({ rows }: { rows: Row[] }) {
    const max = Math.max(...rows.map((row) => size(row.value)), 1);

    return (
        <ul className="flex flex-col gap-3">
            {rows.map((row) => (
                <li key={row.label} className="flex flex-col gap-1">
                    <div className="flex items-baseline justify-between gap-3 text-body">
                        <span className="truncate text-ink">{row.label}</span>
                        <span className="text-ink-muted tabular-nums">
                            {row.text}
                        </span>
                    </div>
                    <div className="h-2 rounded-pill bg-surface-raised">
                        <div
                            className="h-2 rounded-pill bg-primary"
                            style={{
                                width: `${Math.max(2, (size(row.value) / max) * 100)}%`,
                            }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** Credit grows right in success, due grows left in error, from one axis. */
export function DivergingBars({
    rows,
    dueLabel,
    creditLabel,
}: {
    rows: Row[];
    dueLabel: string;
    creditLabel: string;
}) {
    const max = Math.max(...rows.map((row) => Math.abs(size(row.value))), 1);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex justify-between text-caption text-ink-muted">
                <span>{dueLabel}</span>
                <span>{creditLabel}</span>
            </div>
            <ul className="flex flex-col gap-3">
                {rows.map((row) => {
                    const value = size(row.value);
                    const width = `${(Math.abs(value) / max) * 50}%`;

                    return (
                        <li key={row.label} className="flex flex-col gap-1">
                            <div className="flex items-baseline justify-between gap-3 text-body">
                                <span className="truncate text-ink">
                                    {row.label}
                                </span>
                                <span className="tabular-nums">{row.text}</span>
                            </div>
                            <div className="relative h-2 rounded-pill bg-surface-raised">
                                <span
                                    aria-hidden
                                    className="absolute inset-y-0 left-1/2 w-px bg-border"
                                />
                                {value !== 0 && (
                                    <span
                                        className={cn(
                                            'absolute inset-y-0 rounded-pill',
                                            value < 0
                                                ? 'right-1/2 bg-error'
                                                : 'left-1/2 bg-success',
                                        )}
                                        style={{ width }}
                                    />
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
