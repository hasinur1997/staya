import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useTranslate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { photo } from '@/routes/members';

/**
 * A member's photo for lists and tables, or the default user icon when they
 * have none (or may not view it). `version` busts the browser cache when the
 * photo changes.
 */
export function MemberAvatar({
    id,
    name,
    hasPhoto = true,
    version = null,
    className,
}: {
    id: number;
    name: string;
    /** Leave out when unknown: the image is tried and the icon shows if there is none. */
    hasPhoto?: boolean;
    version?: number | null;
    className?: string;
}) {
    const __ = useTranslate();

    return (
        <Avatar className={cn('size-10', className)}>
            {hasPhoto && (
                <AvatarImage
                    src={photo.url(id, { query: { v: version ?? 0 } })}
                    alt={__('Photo of :name', { name })}
                />
            )}
            <AvatarFallback />
        </Avatar>
    );
}
