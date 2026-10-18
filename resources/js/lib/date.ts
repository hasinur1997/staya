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
