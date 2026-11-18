import { Link } from '@inertiajs/react';
import { MemberAvatar } from '@/components/member-avatar';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import { show } from '@/routes/members';

/**
 * A member's name wherever one appears: each word capitalised, medium weight,
 * and a link to the profile for people who may open it. Without the right to
 * view members it stays plain text, so there is never a dead link.
 */
export function MemberName({
    id,
    name,
    linked,
    avatar,
    className,
}: {
    /** Omit when it is not a member, such as "Fund"; the text is then left plain. */
    id?: number | null;
    name: string;
    /** Overrides the default check (the `member.view` permission), e.g. when a page already knows the viewer may open this profile. */
    linked?: boolean;
    /** Show the member's photo (or the default icon) beside the name. */
    avatar?: boolean;
    className?: string;
}) {
    const can = useCan();
    const classes = cn('font-medium text-ink capitalize', className);

    if (id == null) {
        return <span className={className}>{name}</span>;
    }

    const label = !(linked ?? can('member.view')) ? (
        <span className={classes}>{name}</span>
    ) : (
        <Link
            href={show(id)}
            className={cn(classes, 'hover:text-primary hover:underline')}
        >
            {name}
        </Link>
    );

    if (!avatar) {
        return label;
    }

    return (
        <span className="inline-flex items-center gap-3">
            <MemberAvatar id={id} name={name} className="size-8" />
            {label}
        </span>
    );
}
