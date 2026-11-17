import { Monitor, Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { useTranslate } from '@/lib/i18n';

/**
 * Light, dark or system, one click from anywhere. The icon shows the theme
 * that is on screen right now.
 */
export function AppearanceMenu() {
    const __ = useTranslate();
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const Icon = resolvedAppearance === 'dark' ? Moon : Sun;

    const options: { value: Appearance; label: string; icon: typeof Sun }[] = [
        { value: 'light', label: __('Light'), icon: Sun },
        { value: 'dark', label: __('Dark'), icon: Moon },
        { value: 'system', label: __('System'), icon: Monitor },
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={__('Appearance')}
                >
                    <Icon className="size-5" aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) =>
                        updateAppearance(value as Appearance)
                    }
                >
                    {options.map(({ value, label, icon: OptionIcon }) => (
                        <DropdownMenuRadioItem key={value} value={value}>
                            <OptionIcon className="size-4" aria-hidden />
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
