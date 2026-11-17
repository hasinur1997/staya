import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo({ showName = true }: { showName?: boolean }) {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-8 shrink-0" aria-hidden />
            {showName ? (
                <span className="truncate text-label text-ink">{name}</span>
            ) : (
                <span className="sr-only">{name}</span>
            )}
        </>
    );
}
