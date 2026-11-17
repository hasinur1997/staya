import { createInertiaApp, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { startDigitLocalization } from '@/lib/locale';
import AppLayout from '@/layouts/app-layout';
import GuestLayout from '@/layouts/guest-layout';
import SettingsLayout from '@/layouts/settings/layout';

/** The server renders the mess name as the default <title>; read it before Inertia replaces it. */
const appName =
    document.querySelector('title')?.textContent?.trim() ||
    import.meta.env.VITE_APP_NAME ||
    'Laravel';

/*
 * When a save is refused for invalid input, take the person to the first
 * field with a message and say so, since forms use our own messages rather
 * than the browser's.
 */
router.on('error', () => {
    window.setTimeout(() => {
        const message = document.querySelector('[data-slot="input-error"]');
        const field = message?.parentElement;

        if (!field) {
            return;
        }

        field.scrollIntoView({ block: 'center', behavior: 'smooth' });
        field
            .querySelector<HTMLElement>(
                'input:not([type="hidden"]), textarea, button[data-slot]',
            )
            ?.focus({ preventScroll: true });
        toast.error(
            document.documentElement.lang.startsWith('bn')
                ? 'চিহ্নিত ঘরগুলো ঠিক করে আবার চেষ্টা করুন।'
                : 'Please correct the highlighted fields and try again.',
        );
    }, 60);
});

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'design-preview':
                return null;
            case name.startsWith('auth/'):
                return GuestLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        // Progress is a primary-token element (docs/design-system.md).
        color: 'var(--primary)',
    },
});

// This will set light / dark mode on load...
initializeTheme();
startDigitLocalization();
