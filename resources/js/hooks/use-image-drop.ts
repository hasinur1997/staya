import { useState } from 'react';
import type { DragEvent } from 'react';

/**
 * Drag-and-drop for a file target. Spread `handlers` on the element and
 * read `dragging` to highlight it. Only the first dropped file is used.
 */
export function useImageDrop(onFile: (file: File) => void, disabled = false) {
    const [dragging, setDragging] = useState(false);

    const handlers = {
        onDragEnter: (event: DragEvent) => {
            if (!disabled && event.dataTransfer.types.includes('Files')) {
                event.preventDefault();
                setDragging(true);
            }
        },
        onDragOver: (event: DragEvent) => {
            if (!disabled && event.dataTransfer.types.includes('Files')) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
            }
        },
        onDragLeave: (event: DragEvent) => {
            if (!event.currentTarget.contains(event.relatedTarget as Node)) {
                setDragging(false);
            }
        },
        onDrop: (event: DragEvent) => {
            event.preventDefault();
            setDragging(false);

            const file = event.dataTransfer.files[0];

            if (!disabled && file) {
                onFile(file);
            }
        },
    };

    return { dragging, handlers };
}
