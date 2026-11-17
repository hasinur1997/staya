import { Link, router } from '@inertiajs/react';
import { LogOut, Palette, Shield, UserRound } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useTranslate } from '@/lib/i18n';
import { logout } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { User } from '@/types';

type Props = {
    user: User;
};

const links = [
    { title: 'Profile', href: edit(), icon: UserRound },
    { title: 'Security', href: editSecurity(), icon: Shield },
    { title: 'Appearance', href: editAppearance(), icon: Palette },
];

export function UserMenuContent({ user }: Props) {
    const __ = useTranslate();
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-body">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {links.map((link) => (
                    <DropdownMenuItem key={link.title} asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={link.href}
                            prefetch
                            onClick={cleanup}
                        >
                            <link.icon className="mr-2" />
                            {__(link.title)}
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {__('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
