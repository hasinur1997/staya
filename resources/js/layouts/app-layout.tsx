import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppDrawer, AppSidebar } from '@/components/app-sidebar';
import { AppTopBar } from '@/components/app-top-bar';
import { Sheet } from '@/components/ui/sheet';
import { useBreakpoint } from '@/hooks/use-breakpoint';
import type { AppLayoutProps } from '@/types';

/**
 * The signed-in shell from docs/layout.md: sidebar (256px desktop, 72px rail
 * on tablet, drawer on mobile), sticky top bar and the 1200px content column.
 */
export default function AppLayout({
    breadcrumbs = [],
    title,
    children,
}: AppLayoutProps) {
    const breakpoint = useBreakpoint();
    const [drawerOpen, setDrawerOpen] = useState(false);

    useEffect(() => router.on('navigate', () => setDrawerOpen(false)), []);

    useEffect(() => {
        if (breakpoint === 'desktop') {
            setDrawerOpen(false);
        }
    }, [breakpoint]);

    return (
        <Sheet open={drawerOpen} onOpenChange={setDrawerOpen}>
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:text-label focus:text-ink"
            >
                Skip to content
            </a>

            <AppSidebar variant={breakpoint === 'desktop' ? 'full' : 'rail'} />
            <AppDrawer />

            <div className="flex min-h-svh flex-col md:pl-18 lg:pl-64 print:pl-0">
                <AppTopBar
                    breadcrumbs={breadcrumbs}
                    title={title}
                    collapseBreadcrumbs={breakpoint === 'tablet'}
                />
                <main
                    id="main-content"
                    tabIndex={-1}
                    className="mx-auto w-full max-w-content flex-1 px-4 pt-6 pb-6 md:px-6 lg:pb-12"
                >
                    {children}
                </main>
            </div>
        </Sheet>
    );
}
