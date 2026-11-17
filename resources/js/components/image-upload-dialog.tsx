import { ImagePlus, Minus, Plus, Trash2, UploadCloud } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import type { PointerEvent } from 'react';
import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslate } from '@/lib/i18n';
import { useImageDrop } from '@/hooks/use-image-drop';
import { cropToFile, imageProblem } from '@/lib/image';
import { cn } from '@/lib/utils';

type Props = {
    open: boolean;
    /** A file already chosen (dropped on the page), which skips the first step. */
    initialFile?: File | null;
    title: string;
    /** Width over height of the crop frame; the image's own shape when left out. */
    aspect?: number;
    /** Show a round window, for profile pictures. */
    round?: boolean;
    /** The longest edge of the saved image, in pixels. */
    maxWidth?: number;
    /** Accept PDFs, which are kept as they are and never cropped. */
    allowPdf?: boolean;
    /** Offer "Use original" so the picture can be kept uncropped. */
    allowOriginal?: boolean;
    /** The picture on file, shown in the first step. */
    currentSrc?: string | null;
    /** Offer to delete the picture on file. */
    onRemove?: () => void;
    confirmLabel?: string;
    onConfirm: (file: File) => void;
    onClose: () => void;
};

type View = { zoom: number; x: number; y: number };

const MAX_ZOOM = 4;

/**
 * Crop an image: drag it to move, use the slider or the buttons to zoom,
 * and the window shows exactly what is kept. No library; the result is cut
 * out on a canvas and handed back as a new File.
 */
export function ImageUploadDialog({
    open,
    initialFile = null,
    title,
    aspect,
    round = false,
    maxWidth = 1600,
    allowPdf = false,
    allowOriginal = false,
    currentSrc = null,
    onRemove,
    confirmLabel,
    onConfirm,
    onClose,
}: Props) {
    const __ = useTranslate();
    const { mess } = usePage().props;
    const browse = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [problem, setProblem] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setFile(null);
            setProblem(null);

            if (initialFile) {
                receive(initialFile);
            }
        }
    }, [open, initialFile]); // eslint-disable-line react-hooks/exhaustive-deps

    // Check the file, then crop it; a PDF is passed on as it is.
    function receive(next: File) {
        const issue = imageProblem(
            next,
            { maxBytes: mess.maxUploadKb * 1024, allowPdf },
            __,
        );
        setProblem(issue);

        if (issue) {
            return;
        }

        if (next.type === 'application/pdf') {
            onConfirm(next);

            return;
        }

        setFile(next);
    }

    const { dragging, handlers } = useImageDrop(receive);
    const [frameElement, setFrameElement] = useState<HTMLDivElement | null>(
        null,
    );
    const imageRef = useRef<HTMLImageElement>(null);
    const drag = useRef<{ x: number; y: number } | null>(null);
    const [url, setUrl] = useState<string | null>(null);
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(
        null,
    );
    const [frame, setFrame] = useState<{ w: number; h: number } | null>(null);
    const [view, setView] = useState<View>({ zoom: 1, x: 0, y: 0 });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!file) {
            setUrl(null);
            setNatural(null);

            return;
        }

        const next = URL.createObjectURL(file);
        setUrl(next);
        setNatural(null);
        setView({ zoom: 1, x: 0, y: 0 });

        return () => URL.revokeObjectURL(next);
    }, [file]);

    const ratio = aspect ?? (natural ? natural.w / natural.h : 1);

    useLayoutEffect(() => {
        if (!frameElement) {
            setFrame(null);

            return;
        }

        const measure = () =>
            setFrame({
                w: frameElement.clientWidth,
                h: frameElement.clientHeight,
            });
        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(frameElement);

        return () => observer.disconnect();
    }, [frameElement, ratio]);

    const base =
        natural && frame
            ? Math.max(frame.w / natural.w, frame.h / natural.h)
            : 1;
    const scale = base * view.zoom;

    const clamp = useCallback(
        (x: number, y: number, nextScale: number) => {
            if (!natural || !frame) {
                return { x, y };
            }

            return {
                x: Math.min(0, Math.max(frame.w - natural.w * nextScale, x)),
                y: Math.min(0, Math.max(frame.h - natural.h * nextScale, y)),
            };
        },
        [natural, frame],
    );

    // Centre the picture once both it and the window are measured.
    useEffect(() => {
        if (natural && frame) {
            const initial = Math.max(frame.w / natural.w, frame.h / natural.h);
            setView({
                zoom: 1,
                ...clamp(
                    (frame.w - natural.w * initial) / 2,
                    (frame.h - natural.h * initial) / 2,
                    initial,
                ),
            });
        }
    }, [natural, frame?.w, frame?.h]); // eslint-disable-line react-hooks/exhaustive-deps

    const zoomTo = (zoom: number) => {
        if (!natural || !frame) {
            return;
        }

        const next = Math.min(MAX_ZOOM, Math.max(1, zoom));
        const nextScale = base * next;
        const centreX = (frame.w / 2 - view.x) / scale;
        const centreY = (frame.h / 2 - view.y) / scale;

        setView({
            zoom: next,
            ...clamp(
                frame.w / 2 - centreX * nextScale,
                frame.h / 2 - centreY * nextScale,
                nextScale,
            ),
        });
    };

    const down = (event: PointerEvent) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        drag.current = { x: event.clientX, y: event.clientY };
    };

    const move = (event: PointerEvent) => {
        if (!drag.current) {
            return;
        }

        const dx = event.clientX - drag.current.x;
        const dy = event.clientY - drag.current.y;
        drag.current = { x: event.clientX, y: event.clientY };
        setView((current) => ({
            ...current,
            ...clamp(current.x + dx, current.y + dy, scale),
        }));
    };

    const save = async () => {
        if (!file || !imageRef.current || !frame) {
            return;
        }

        setSaving(true);

        try {
            onConfirm(
                await cropToFile(
                    imageRef.current,
                    {
                        sx: -view.x / scale,
                        sy: -view.y / scale,
                        sw: frame.w / scale,
                        sh: frame.h / scale,
                    },
                    file,
                    maxWidth,
                ),
            );
        } finally {
            setSaving(false);
        }
    };

    const sizeMb = Math.floor(mess.maxUploadKb / 1024);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-xl">
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>
                    {file
                        ? __(
                              'Drag the picture to move it, and zoom to choose what to keep.',
                          )
                        : __('Upload a picture, then adjust it to fit.')}
                </DialogDescription>

                {!file ? (
                    <>
                        <input
                            ref={browse}
                            type="file"
                            accept={
                                allowPdf
                                    ? 'image/jpeg,image/png,image/webp,application/pdf'
                                    : 'image/jpeg,image/png,image/webp'
                            }
                            className="sr-only"
                            tabIndex={-1}
                            aria-label={__('Upload from computer')}
                            onChange={(event) => {
                                const next = event.target.files?.[0];
                                event.target.value = '';

                                if (next) {
                                    receive(next);
                                }
                            }}
                        />
                        <div
                            {...handlers}
                            className={cn(
                                'flex flex-col items-center gap-3 rounded-lg border border-dashed border-border bg-surface-raised/50 px-6 py-10 text-center transition-colors',
                                dragging && 'border-primary bg-primary/5',
                            )}
                        >
                            {currentSrc ? (
                                <img
                                    src={currentSrc}
                                    alt=""
                                    className={cn(
                                        'size-24 object-cover',
                                        round ? 'rounded-full' : 'rounded-lg',
                                    )}
                                />
                            ) : (
                                <ImagePlus
                                    className="size-10 text-ink-muted"
                                    aria-hidden
                                />
                            )}
                            <p className="text-label text-ink">
                                {dragging
                                    ? __('Drop it here')
                                    : __('Drag and drop a file here')}
                            </p>
                            <p className="text-caption text-ink-muted">
                                {__('or')}
                            </p>
                            <Button
                                type="button"
                                onClick={() => browse.current?.click()}
                            >
                                <UploadCloud />
                                {__('Upload from computer')}
                            </Button>
                            <p className="text-caption text-ink-muted">
                                {allowPdf
                                    ? __(
                                          'JPG, PNG, WebP or PDF, up to :size MB',
                                          { size: sizeMb },
                                      )
                                    : __('JPG, PNG or WebP, up to :size MB', {
                                          size: sizeMb,
                                      })}
                            </p>
                        </div>
                        {problem && (
                            <p role="alert" className="text-caption text-error">
                                {problem}
                            </p>
                        )}
                        <DialogFooter className="gap-2">
                            {onRemove && currentSrc && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-error sm:mr-auto"
                                    onClick={onRemove}
                                >
                                    <Trash2 />
                                    {__('Remove current picture')}
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                {__('Cancel')}
                            </Button>
                        </DialogFooter>
                    </>
                ) : (
                    <>
                        <div
                            ref={setFrameElement}
                            className={cn(
                                'relative mx-auto w-full max-w-md cursor-grab touch-none overflow-hidden rounded-lg bg-surface-raised select-none active:cursor-grabbing',
                                round && 'max-w-xs rounded-full',
                            )}
                            style={{
                                aspectRatio: round ? 1 : ratio,
                                maxHeight: '55vh',
                            }}
                            onPointerDown={down}
                            onPointerMove={move}
                            onPointerUp={() => (drag.current = null)}
                            onPointerCancel={() => (drag.current = null)}
                        >
                            {url && (
                                <img
                                    ref={imageRef}
                                    src={url}
                                    alt=""
                                    draggable={false}
                                    onLoad={(event) =>
                                        setNatural({
                                            w: event.currentTarget.naturalWidth,
                                            h: event.currentTarget
                                                .naturalHeight,
                                        })
                                    }
                                    className="absolute top-0 left-0 max-w-none"
                                    style={{
                                        width: natural
                                            ? natural.w * scale
                                            : undefined,
                                        height: natural
                                            ? natural.h * scale
                                            : undefined,
                                        transform: `translate(${view.x}px, ${view.y}px)`,
                                        visibility:
                                            natural && frame
                                                ? 'visible'
                                                : 'hidden',
                                    }}
                                />
                            )}
                            <div
                                aria-hidden
                                className="pointer-events-none absolute inset-0 rounded-[inherit] ring-1 ring-border ring-inset"
                            />
                        </div>

                        <div className="mx-auto flex w-full max-w-md items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={__('Zoom out')}
                                onClick={() => zoomTo(view.zoom - 0.25)}
                                disabled={view.zoom <= 1}
                            >
                                <Minus />
                            </Button>
                            <input
                                type="range"
                                min={1}
                                max={MAX_ZOOM}
                                step={0.01}
                                value={view.zoom}
                                onChange={(event) =>
                                    zoomTo(Number(event.target.value))
                                }
                                aria-label={__('Zoom')}
                                className="h-2 flex-1 cursor-pointer accent-primary"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label={__('Zoom in')}
                                onClick={() => zoomTo(view.zoom + 0.25)}
                                disabled={view.zoom >= MAX_ZOOM}
                            >
                                <Plus />
                            </Button>
                        </div>

                        <DialogFooter className="gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                className="sm:mr-auto"
                                onClick={() => setFile(null)}
                            >
                                {__('Choose another')}
                            </Button>
                            {allowOriginal && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onConfirm(file)}
                                >
                                    {__('Use original')}
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                {__('Cancel')}
                            </Button>
                            <Button
                                type="button"
                                onClick={save}
                                disabled={saving || !natural || !frame}
                            >
                                {confirmLabel ?? __('Crop and use')}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
