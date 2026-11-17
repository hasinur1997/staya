import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo({ showName = true }: { showName?: boolean }) {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-8 shrink-0" aria-hidden />
            {showName ? (
                <span className="flex min-w-0 flex-col">
                    <span className="brand-wordmark truncate">Staya</span>
                    <span className="truncate text-caption text-ink-muted">
                        {name}
                    </span>
                </span>
            ) : (
                <span className="sr-only">{name}</span>
            )}
        </>
    );
}
