import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { localizedText } from '@/lib/locale';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();
    const name = localizedText(user.name, user.name_bn);

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={name} />
                <AvatarFallback className="rounded-lg bg-primary-soft text-ink">
                    {getInitials(name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-small">
                <span className="truncate font-medium">{name}</span>
                {showEmail && (
                    <span className="truncate text-caption text-ink-muted">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
