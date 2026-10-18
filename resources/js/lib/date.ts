/*
 * Timestamps arrive from the server in UTC (ISO 8601) and are shown in
 * Asia/Dhaka, whatever the viewer's own clock says.
 */
const dateTime = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Asia/Dhaka',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
});

/** "18 Oct 2026, 3:05 pm" in Asia/Dhaka. */
export function formatDateTime(iso: string): string {
    return dateTime.format(new Date(iso));
}

const businessDate = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'UTC',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/**
 * "18 Oct 2026" for a Y-m-d business date. It is already a Dhaka calendar
 * day, so it is formatted as-is, with no timezone shift.
 */
export function formatBusinessDate(date: string): string {
    return businessDate.format(new Date(`${date}T00:00:00Z`));
}
