import { useEffect, useState } from 'react';

function secondsBetween(from: string, to: string): number {
    return Math.max(0, Math.ceil((Date.parse(to) - Date.parse(from)) / 1000));
}

/**
 * Seconds left until `closesAt`, counted from the server's own "now" plus
 * the time this page has been open. The viewer's clock is never read, and
 * the server still decides whether the window is open.
 */
export function useCountdown(
    closesAt: string | null,
    serverNow: string,
): number | null {
    const [remaining, setRemaining] = useState<number | null>(() =>
        closesAt ? secondsBetween(serverNow, closesAt) : null,
    );

    useEffect(() => {
        if (!closesAt) {
            return;
        }

        const openedAt = performance.now();
        const atLoad = Date.parse(closesAt) - Date.parse(serverNow);
        const timer = window.setInterval(() => {
            const left = atLoad - (performance.now() - openedAt);

            setRemaining(Math.max(0, Math.ceil(left / 1000)));
        }, 1000);

        return () => window.clearInterval(timer);
    }, [closesAt, serverNow]);

    return closesAt ? remaining : null;
}

/** "12:05" for 725 seconds. */
export function formatCountdown(seconds: number): string {
    const minutes = Math.floor(seconds / 60);

    return `${minutes}:${String(seconds % 60).padStart(2, '0')}`;
}
