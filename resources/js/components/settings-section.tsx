import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

/**
 * One block of a settings page: the title and a short explanation on the
 * left, the fields in a card on the right. Stacks on small screens.
 */
export function SettingsSection({
    title,
    description,
    tone = 'default',
    children,
}: {
    title: string;
    description?: string;
    /** `danger` is for irreversible actions. */
    tone?: 'default' | 'danger';
    children: ReactNode;
}) {
    return (
        <section className="grid gap-4 lg:grid-cols-3 lg:gap-8">
            <div className="flex flex-col gap-1">
                <h2
                    className={cn(
                        'text-title',
                        tone === 'danger' ? 'text-error' : 'text-ink',
                    )}
                >
                    {title}
                </h2>
                {description && (
                    <p className="text-body text-ink-muted">{description}</p>
                )}
            </div>
            <Card
                className={cn(
                    'gap-4 px-4 lg:col-span-2',
                    tone === 'danger' && 'border-error/40',
                )}
            >
                {children}
            </Card>
        </section>
    );
}
