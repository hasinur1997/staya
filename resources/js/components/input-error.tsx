import { CircleAlert } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * A field's validation message, in our own design (not the browser's
 * required bubble). app.css gives the control above it the error border.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            role="alert"
            data-slot="input-error"
            {...props}
            className={cn(
                'flex items-start gap-1.5 text-caption text-error',
                className,
            )}
        >
            <CircleAlert className="mt-px size-3.5 shrink-0" aria-hidden />
            <span>{message}</span>
        </p>
    ) : null;
}
