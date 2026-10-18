import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

export type Replacements = Record<string, string | number>;

/**
 * Laravel-style lookup: the English string is the key, `lang/{locale}.json`
 * supplies other languages, and `:name` placeholders are replaced.
 */
export function translate(
    translations: Record<string, string>,
    key: string,
    replace: Replacements = {},
): string {
    return Object.entries(replace).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
        translations[key] ?? key,
    );
}

/** Returns `__()` bound to the translations shared for this visit. */
export function useTranslate(): (
    key: string,
    replace?: Replacements,
) => string {
    const { translations } = usePage().props;

    return useCallback(
        (key: string, replace?: Replacements) =>
            translate(translations ?? {}, key, replace),
        [translations],
    );
}
