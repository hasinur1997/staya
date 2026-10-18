import { useSyncExternalStore } from 'react';

export type Breakpoint = 'mobile' | 'tablet' | 'desktop';

// Matches Tailwind's md (768px) and lg (1024px) and docs/layout.md.
const queries =
    typeof window === 'undefined'
        ? undefined
        : {
              tablet: window.matchMedia('(min-width: 768px)'),
              desktop: window.matchMedia('(min-width: 1024px)'),
          };

function subscribe(callback: () => void) {
    if (!queries) {
        return () => {};
    }

    queries.tablet.addEventListener('change', callback);
    queries.desktop.addEventListener('change', callback);

    return () => {
        queries.tablet.removeEventListener('change', callback);
        queries.desktop.removeEventListener('change', callback);
    };
}

function getSnapshot(): Breakpoint {
    if (queries?.desktop.matches) {
        return 'desktop';
    }

    return queries?.tablet.matches ? 'tablet' : 'mobile';
}

export function useBreakpoint(): Breakpoint {
    return useSyncExternalStore(subscribe, getSnapshot, () => 'desktop');
}
