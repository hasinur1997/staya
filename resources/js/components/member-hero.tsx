import { router } from '@inertiajs/react';
import { Camera, ImagePlus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ActionButton } from '@/components/action-button';
import { ImageUploadDialog } from '@/components/image-upload-dialog';
import { MemberStatusBadge } from '@/components/member-status-badge';
import { MoreMenu } from '@/components/page-header';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useImageDrop } from '@/hooks/use-image-drop';
import { useInitials } from '@/hooks/use-initials';
import { formatBusinessDate } from '@/lib/date';
import { useTranslate } from '@/lib/i18n';
import { cn } from '@/lib/utils';
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

    const [dialog, setDialog] = useState<{
        field: 'photo' | 'banner';
        file: File | null;
    } | null>(null);

    const bannerDrop = useImageDrop(
        (file) => setDialog({ field: 'banner', file }),
        !canEdit,
    );
    const photoDrop = useImageDrop(
        (file) => setDialog({ field: 'photo', file }),
        !canEdit,
    );

    const finish = (data: Record<string, unknown>) => {
        send(data);
        setDialog(null);
    };

    return (
        <section className="rounded-xl flex flex-col overflow-hidden border bg-card">
            <div
                {...bannerDrop.handlers}
                className={cn(
                    'relative h-36 bg-gradient-to-r from-primary/15 to-highlight/20 md:h-48',
                    bannerDrop.dragging && 'ring-2 ring-primary ring-inset',
                )}
            >
                {canEdit && bannerDrop.dragging && (
                    <p className="absolute inset-0 z-10 flex items-center justify-center bg-card/80 text-label text-ink">
                        {__('Drop the banner here')}
                    </p>
                )}
                {member.has_banner && (
                    <img
                        src={banner.url(member.id, { query: { v: version } })}
                        alt=""
                        className="size-full object-cover"
                    />
                )}
                {canEdit && (
                    <div className="absolute top-3 right-3 print:hidden">
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            aria-label={__('Change banner')}
                            title={__('Change banner')}
                            onClick={() =>
                                setDialog({ field: 'banner', file: null })
                            }
                        >
                            <ImagePlus />
                        </Button>
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-4 px-4 pb-4 md:flex-row md:items-end md:gap-6 md:px-6 md:pb-6">
                <div
                    {...photoDrop.handlers}
                    className={cn(
                        'relative -mt-12 w-fit rounded-full md:-mt-16',
                        photoDrop.dragging && 'ring-2 ring-primary',
                    )}
                >
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
                        <Button
                            type="button"
                            size="icon"
                            variant="outline"
                            className="absolute right-0 bottom-0 size-8 rounded-full print:hidden"
                            aria-label={__('Change profile picture')}
                            title={__('Change profile picture')}
                            onClick={() =>
                                setDialog({ field: 'photo', file: null })
                            }
                        >
                            <Camera />
                        </Button>
                    )}
                </div>

                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h1 className="text-heading text-ink capitalize">
                            {member.name}
                        </h1>
                        <MemberStatusBadge
                            status={member.status}
                            label={member.status_label}
                        />
                    </div>
                    <p className="text-caption text-ink-muted">
                        {[member.email, member.phone]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    {currentStay && (
                        <p className="text-caption text-ink-muted">
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

            <ImageUploadDialog
                open={dialog !== null}
                initialFile={dialog?.file ?? null}
                title={
                    dialog?.field === 'banner'
                        ? __('Banner')
                        : __('Profile picture')
                }
                aspect={dialog?.field === 'banner' ? 4 : 1}
                round={dialog?.field === 'photo'}
                maxWidth={dialog?.field === 'banner' ? 1600 : 800}
                confirmLabel={__('Crop and upload')}
                currentSrc={
                    dialog?.field === 'banner'
                        ? member.has_banner
                            ? banner.url(member.id, { query: { v: version } })
                            : null
                        : member.has_photo
                          ? photo.url(member.id, { query: { v: version } })
                          : null
                }
                onRemove={() =>
                    finish(
                        dialog?.field === 'banner'
                            ? { remove_banner: 1 }
                            : { remove_photo: 1 },
                    )
                }
                onClose={() => setDialog(null)}
                onConfirm={(file) =>
                    finish(dialog ? { [dialog.field]: file } : {})
                }
            />
        </section>
    );
}
