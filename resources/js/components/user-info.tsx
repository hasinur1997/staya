import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { localizedText } from '@/lib/locale';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const name = localizedText(user.name, user.name_bn);

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={name} />
                <AvatarFallback className="rounded-lg bg-primary-soft text-ink" />
            </Avatar>
            <div className="grid flex-1 text-left text-body">
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
