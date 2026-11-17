/*
 * The language on screen. The server sends it with every page; the formatters
 * below read it so dates, month names and digits follow the same choice.
 * Changing language reloads the page, so this is set once per load.
 */
export type Locale = 'en' | 'bn';

/** The server writes the chosen language on <html lang>, before any script runs. */
let activeLocale: Locale =
    typeof document !== 'undefined' &&
    document.documentElement.lang.startsWith('bn')
        ? 'bn'
        : 'en';

export function getActiveLocale(): Locale {
    return activeLocale;
}

/** The BCP 47 tag Intl needs for the active language. */
export function intlLocale(): string {
    return activeLocale === 'bn' ? 'bn-BD' : 'en-GB';
}

const BANGLA_DIGITS = '০১২৩৪৫৬৭৮৯';

/** 0 1 2 3 4 5 6 7 8 9 become ০ ১ ২ ৩ ৪ ৫ ৬ ৭ ৮ ৯. */
export function toBanglaDigits(text: string): string {
    return text.replace(/[0-9]/g, (digit) => BANGLA_DIGITS[Number(digit)]);
}

/** Digits in the active language; English text is returned untouched. */
export function localizeDigits(text: string): string {
    return activeLocale === 'bn' ? toBanglaDigits(text) : text;
}

/*
 * Anything that is a code rather than a quantity keeps its Latin digits:
 * email addresses, IP addresses, permission keys in <code>.
 */
const KEEPS_LATIN = /@|\b\d{1,3}(\.\d{1,3}){3}\b/;
const SKIPPED_TAGS = new Set(['SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT', 'CODE']);

function convertText(node: Text): void {
    const value = node.nodeValue;

    if (!value || !/[0-9]/.test(value) || KEEPS_LATIN.test(value)) {
        return;
    }

    const parent = node.parentElement;

    if (
        !parent ||
        SKIPPED_TAGS.has(parent.tagName) ||
        parent.closest('[data-keep-digits]')
    ) {
        return;
    }

    node.nodeValue = toBanglaDigits(value);
}

function convertTree(root: Node): void {
    if (root.nodeType === Node.TEXT_NODE) {
        convertText(root as Text);

        return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const texts: Text[] = [];

    while (walker.nextNode()) {
        texts.push(walker.currentNode as Text);
    }

    texts.forEach(convertText);
}

let observer: MutationObserver | null = null;

/**
 * Shows Bangla digits for every number on screen, including ones React
 * writes later. Done on the rendered text so no screen has to remember it.
 */
export function startDigitLocalization(): void {
    if (activeLocale !== 'bn' || observer || typeof document === 'undefined') {
        return;
    }

    convertTree(document.body);

    observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.type === 'characterData') {
                convertText(mutation.target as Text);
            } else {
                mutation.addedNodes.forEach(convertTree);
            }
        }
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true,
    });
}
