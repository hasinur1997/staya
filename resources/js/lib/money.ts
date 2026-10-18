/**
 * Formats a server decimal string ("12450.5", "-980.50") as "৳ 12,450.50".
 * String work only: money is never turned into a float on the client.
 */
export function formatMoney(value: string, { signed = false } = {}): string {
    const trimmed = value.trim();
    const negative = trimmed.startsWith('-');
    const [whole = '0', fraction = ''] = trimmed
        .replace(/^[-+]/, '')
        .split('.');
    const grouped = (whole.replace(/^0+(?=\d)/, '') || '0').replace(
        /\B(?=(\d{3})+(?!\d))/g,
        ',',
    );
    const isZero = /^0*$/.test(whole) && /^0*$/.test(fraction);
    const sign = negative && !isZero ? '−' : signed && !isZero ? '+' : '';

    return `${sign}৳ ${grouped}.${fraction.padEnd(2, '0').slice(0, 2)}`;
}
