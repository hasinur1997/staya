import { router } from '@inertiajs/react';
import { Camera, ImagePlus, Trash2 } from 'lucide-react';
import { useRef } from 'react';
import { toast } from 'sonner';
import { ActionButton } from '@/components/action-button';
import { MemberStatusBadge } from '@/components/member-status-badge';
import { MoreMenu } from '@/components/page-header';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { banner, photo } from '@/routes/members';
import { update as updateSection } from '@/routes/members/sections';
import type { MemberProfile, PageAction } from '@/types';

type Props = {
    member: MemberProfile;
    canEdit: boolean;
    actions: PageAction[];
    menuActions: PageAction[];
};

/**
 * The banner, profile picture, name and status at the top of a member's
 * profile. With edit rights, the pencil on the banner changes it and the
 * camera on the picture changes that; both upload straight away.
 */
export function MemberHero({ member, canEdit, actions, menuActions }: Props) {
    const __ = useTranslate();
    const getInitials = useInitials();
    const bannerInput = useRef<HTMLInputElement>(null);
    const photoInput = useRef<HTMLInputElement>(null);
    const currentStay = member.stays.find((stay) => stay.left_on === null);
    const version = member.media_version ?? undefined;

    const send = (data: Record<string, unknown>) =>
        router.post(
            updateSection.url({ member: member.id, section: 'images' }),
            { _method: 'patch', ...data },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ??
                            __('The picture could not be saved.'),
                    ),
            },
        );

    const upload = (field: 'photo' | 'banner', file: File | undefined) => {
        if (file) {
            send({ [field]: file });
        }
    };

    return (
        <section className="rounded-xl flex flex-col overflow-hidden border bg-card">
            <div className="relative h-36 bg-gradient-to-r from-primary/15 to-highlight/20 md:h-48">
                {member.has_banner && (
                    <img
                        src={banner.url(member.id, { query: { v: version } })}
                        alt=""
                        className="size-full object-cover"
                    />
                )}
                {canEdit && (
                    <div className="absolute top-3 right-3 flex gap-2 print:hidden">
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            aria-label={__('Change banner')}
                            title={__('Change banner')}
                            onClick={() => bannerInput.current?.click()}
                        >
                            <ImagePlus />
                        </Button>
                        {member.has_banner && (
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                aria-label={__('Remove banner')}
                                title={__('Remove banner')}
                                onClick={() => send({ remove_banner: 1 })}
                            >
                                <Trash2 />
                            </Button>
                        )}
                        <input
                            ref={bannerInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="sr-only"
                            tabIndex={-1}
                            onChange={(event) => {
                                upload('banner', event.target.files?.[0]);
                                event.target.value = '';
                            }}
                        />
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-4 px-4 pb-4 md:flex-row md:items-end md:gap-6 md:px-6 md:pb-6">
                <div className="relative -mt-12 w-fit md:-mt-16">
                    <Avatar className="size-24 border-4 border-card md:size-32">
                        {member.has_photo && (
                            <AvatarImage
                                src={photo.url(member.id, {
                                    query: { v: version },
                                })}
                                alt={__('Photo of :name', {
                                    name: member.name,
                                })}
                            />
                        )}
                        <AvatarFallback className="text-heading">
                            {getInitials(member.name)}
                        </AvatarFallback>
                    </Avatar>
                    {canEdit && (
                        <>
                            <Button
                                type="button"
                                size="icon"
                                variant="outline"
                                className="absolute right-0 bottom-0 size-8 rounded-full print:hidden"
                                aria-label={__('Change profile picture')}
                                title={__('Change profile picture')}
                                onClick={() => photoInput.current?.click()}
                            >
                                <Camera />
                            </Button>
                            <input
                                ref={photoInput}
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="sr-only"
                                tabIndex={-1}
                                onChange={(event) => {
                                    upload('photo', event.target.files?.[0]);
                                    event.target.value = '';
                                }}
                            />
                        </>
                    )}
                </div>

                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 className="text-heading text-ink">{member.name}</h1>
                        <MemberStatusBadge
                            status={member.status}
                            label={member.status_label}
                        />
                    </div>
                    <p className="text-small text-ink-muted">
                        {[member.email, member.phone]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    {currentStay && (
                        <p className="text-small text-ink-muted">
                            {__('Living in the mess since :date', {
                                date: formatBusinessDate(currentStay.joined_on),
                            })}
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-2 print:hidden">
                    {actions.map((action) => (
                        <ActionButton
                            key={action.label}
                            action={action}
                            variant="outline"
                        />
                    ))}
                    <MoreMenu actions={menuActions} />
                </div>
            </div>
        </section>
    );
}
