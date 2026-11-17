import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslate } from '@/lib/i18n';
import type { Paginated } from '@/types';

function PageLink({ href, label }: { href: string | null; label: string }) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveScroll>
                {label}
            </Link>
        </Button>
    );
}

/** Previous / next links for a paginated list; nothing for one page. */
export function Pager<T>({ page }: { page: Paginated<T> }) {
    const __ = useTranslate();

    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center gap-3">
            <PageLink href={page.prev_page_url} label={__('Previous')} />
            <span className="text-caption text-ink-muted">
                {__('Page :page of :pages', {
                    page: page.current_page,
                    pages: page.last_page,
                })}
            </span>
            <PageLink href={page.next_page_url} label={__('Next')} />
        </div>
    );
}
