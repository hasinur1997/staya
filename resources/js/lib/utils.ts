import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// Teach tailwind-merge the design-system type styles, radii and shadow so
// e.g. `text-body text-ink` is not collapsed as two text colours.
const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: [
                'display',
                'heading',
                'title',
                'body',
                'label',
                'caption',
                'money',
            ],
            radius: ['pill'],
            shadow: ['card'],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}
