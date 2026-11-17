/** Image types the server accepts for photos and banners. */
export const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export type ImageRules = {
    /** Largest file in bytes. */
    maxBytes: number;
    /** Accept PDFs, which are kept as they are and never cropped. */
    allowPdf?: boolean;
};

/** The reason a file is not acceptable, or null when it is fine. */
export function imageProblem(
    file: File,
    { maxBytes, allowPdf = false }: ImageRules,
    t: (text: string, replace?: Record<string, string | number>) => string,
): string | null {
    const allowed = allowPdf
        ? [...PHOTO_TYPES, 'application/pdf']
        : PHOTO_TYPES;

    if (!allowed.includes(file.type)) {
        return allowPdf
            ? t('Choose a JPG, PNG, WebP or PDF file.')
            : t('Choose a JPG, PNG or WebP image.');
    }

    if (file.size > maxBytes) {
        return t('The file is too large. The limit is :size MB.', {
            size: Math.floor(maxBytes / 1024 / 1024),
        });
    }

    return null;
}

export type CropRect = { sx: number; sy: number; sw: number; sh: number };

/**
 * Cut the chosen part out of the image into a new file. The output keeps
 * the source type (JPEG, PNG or WebP) and is scaled down to `maxWidth`.
 */
export function cropToFile(
    image: HTMLImageElement,
    rect: CropRect,
    source: File,
    maxWidth: number,
): Promise<File> {
    const width = Math.min(Math.round(rect.sw), maxWidth);
    const height = Math.max(1, Math.round((width * rect.sh) / rect.sw));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');

    if (!context) {
        return Promise.reject(new Error('Canvas is not available.'));
    }

    context.imageSmoothingQuality = 'high';
    context.drawImage(
        image,
        rect.sx,
        rect.sy,
        rect.sw,
        rect.sh,
        0,
        0,
        width,
        height,
    );

    const type = PHOTO_TYPES.includes(source.type) ? source.type : 'image/jpeg';
    const extension = type === 'image/jpeg' ? 'jpg' : type.split('/')[1];
    const base = source.name.replace(/\.[^.]+$/, '') || 'image';

    return new Promise((resolve, reject) =>
        canvas.toBlob(
            (blob) =>
                blob
                    ? resolve(
                          new File([blob], `${base}.${extension}`, { type }),
                      )
                    : reject(new Error('The image could not be cropped.')),
            type,
            0.92,
        ),
    );
}
