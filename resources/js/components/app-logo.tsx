import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo({ showName = true }: { showName?: boolean }) {
    const { name } = usePage().props;

    return (
        <>
            <span className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-primary">
                <AppLogoIcon
                    className="size-5 fill-current text-on-primary"
                    aria-hidden
                />
            </span>
            {showName ? (
                <span className="truncate text-label text-ink">{name}</span>
            ) : (
                <span className="sr-only">{name}</span>
            )}
        </>
    );
}
