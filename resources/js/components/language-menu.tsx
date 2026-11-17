import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslate } from '@/lib/i18n';
import { update } from '@/routes/locale';

/** English or বাংলা. The choice is kept in a cookie and the page reloads in it. */
export function LanguageMenu() {
    const __ = useTranslate();
    const { locale, locales } = usePage().props;

    const choose = (next: string) => {
        if (next !== locale) {
            router.post(
                update.url(),
                { locale: next },
                {
                    preserveScroll: true,
                    onSuccess: () => window.location.reload(),
                },
            );
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" aria-label={__('Language')}>
                    <Languages className="size-5" aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                <DropdownMenuRadioGroup value={locale} onValueChange={choose}>
                    {Object.entries(locales).map(([code, name]) => (
                        <DropdownMenuRadioItem key={code} value={code}>
                            {name}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
