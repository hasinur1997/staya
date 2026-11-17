import { FileText, ImagePlus, Trash2, UploadCloud } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { ImageUploadDialog } from '@/components/image-upload-dialog';
import { Button } from '@/components/ui/button';
import { useImageDrop } from '@/hooks/use-image-drop';
import { useTranslate } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    /** Field name posted with the form. */
    name: string;
    id?: string;
    /** Width over height of the kept area; the image's own shape when left out. */
    aspect?: number;
    /** A round window and preview, for profile pictures. */
    round?: boolean;
    /** Longest edge of the saved image, in pixels. */
    maxWidth?: number;
    /** Accept PDFs (kept as they are). */
    allowPdf?: boolean;
    required?: boolean;
    /** The picture already on file, shown until a new one is chosen. */
    currentSrc?: string | null;
    /** Name of what is uploaded, e.g. "photo" or "receipt". */
    subject?: string;
    error?: string;
};

/**
 * A photo field for forms. The button (or a file dropped on the box) opens
 * the upload dialog, where the picture is dropped or picked, then cropped
 * and resized. The result is posted under `name` like any file input.
 */
export function ImageDropzone({
    name,
    id = name,
    aspect,
    round = false,
    maxWidth = 1600,
    allowPdf = false,
    required = false,
    currentSrc = null,
    subject,
    error,
}: Props) {
    const __ = useTranslate();
    const input = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [dropped, setDropped] = useState<File | null>(null);
    const [chosen, setChosen] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const label = subject ?? __('picture');

    useEffect(() => {
        if (!chosen || chosen.type === 'application/pdf') {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(chosen);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [chosen]);

    const setFile = (file: File | null) => {
        const transfer = new DataTransfer();

        if (file) {
            transfer.items.add(file);
        }

        if (input.current) {
            input.current.files = transfer.files;
        }

        setChosen(file);
    };

    const { dragging, handlers } = useImageDrop((file) => {
        setDropped(file);
        setOpen(true);
    });
    const shown = preview ?? currentSrc;

    return (
        <div className="flex flex-col gap-2">
            {/* The cropped file travels in this input, so any <Form> posts it. */}
            <input
                ref={input}
                id={id}
                name={name}
                type="file"
                className="sr-only"
                tabIndex={-1}
                required={required && !currentSrc}
                onChange={() => undefined}
            />

            <div
                {...handlers}
                className={cn(
                    'flex flex-col items-center gap-3 rounded-lg border border-dashed border-border bg-surface-raised/50 p-4 text-center transition-colors sm:flex-row sm:text-left',
                    dragging && 'border-primary bg-primary/5',
                )}
            >
                <div
                    className={cn(
                        'flex size-20 shrink-0 items-center justify-center overflow-hidden border bg-card text-ink-muted',
                        round ? 'rounded-full' : 'rounded-lg',
                    )}
                >
                    {shown ? (
                        <img
                            src={shown}
                            alt=""
                            className="size-full object-cover"
                        />
                    ) : chosen ? (
                        <FileText className="size-8" aria-hidden />
                    ) : (
                        <ImagePlus className="size-8" aria-hidden />
                    )}
                </div>

                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <p className="text-label text-ink">
                        {chosen
                            ? chosen.name
                            : dragging
                              ? __('Drop it to upload')
                              : __('No :subject chosen', { subject: label })}
                    </p>
                    <p className="text-caption text-ink-muted">
                        {__('Drag a file here or use the button.')}
                    </p>
                    <div className="flex flex-wrap justify-center gap-2 pt-1 sm:justify-start">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => {
                                setDropped(null);
                                setOpen(true);
                            }}
                        >
                            <UploadCloud />
                            {chosen || currentSrc
                                ? __('Change :subject', { subject: label })
                                : __('Upload :subject', { subject: label })}
                        </Button>
                        {chosen && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setFile(null)}
                            >
                                <Trash2 />
                                {__('Remove')}
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <InputError message={error} />

            <ImageUploadDialog
                open={open}
                initialFile={dropped}
                title={__('Upload :subject', { subject: label })}
                aspect={aspect}
                round={round}
                maxWidth={maxWidth}
                allowPdf={allowPdf}
                allowOriginal={aspect === undefined}
                currentSrc={preview ?? currentSrc}
                onRemove={
                    chosen
                        ? () => {
                              setFile(null);
                              setOpen(false);
                          }
                        : undefined
                }
                onClose={() => setOpen(false)}
                onConfirm={(file) => {
                    setFile(file);
                    setOpen(false);
                }}
            />
        </div>
    );
}
