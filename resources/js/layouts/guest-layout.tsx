import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import type { GuestLayoutProps } from '@/types';

/** Log in, sign up and password pages: one centred card, no app shell. */
export default function GuestLayout({
    children,
    title,
    description,
    wide = false,
}: GuestLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background px-4 py-12 md:px-6">
            <Link href={home()} className="flex items-center gap-3">
                <AppLogo />
            </Link>

            <Card
                className={cn(
                    'w-full gap-6 px-4 md:px-8 md:py-8',
                    wide ? 'max-w-160' : 'max-w-110',
                )}
            >
                {(title || description) && (
                    <div className="flex flex-col gap-1">
                        {title && <h1 className="text-heading">{title}</h1>}
                        {description && (
                            <p className="text-small text-ink-muted">
                                {description}
                            </p>
                        )}
                    </div>
                )}
                {children}
            </Card>
        </div>
    );
}
