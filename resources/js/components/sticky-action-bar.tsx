import type { ReactNode } from 'react';

/**
 * Mobile only: pins the view's single primary action to the bottom of the
 * screen. app.css pads <main> while one is present so nothing hides behind it.
 */
export function StickyActionBar({ children }: { children: ReactNode }) {
    return (
        <div
            data-slot="sticky-action-bar"
            className="fixed inset-x-0 bottom-0 z-20 border-t bg-background px-4 pt-4 pb-[calc(var(--space-4)+env(safe-area-inset-bottom))] md:hidden print:hidden"
        >
            {children}
        </div>
    );
}
