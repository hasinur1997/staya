import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import type { PageAction } from '@/types';

type Props = {
    action: PageAction;
} & Pick<ComponentProps<typeof Button>, 'variant' | 'size' | 'className'>;

/** Renders a PageAction as a link, a form submit or a plain button. */
export function ActionButton({ action, ...props }: Props) {
    const content = (
        <>
            {action.icon && <action.icon aria-hidden />}
            {action.label}
        </>
    );

    if (action.href && !action.disabled) {
        return (
            <Button asChild {...props}>
                <Link href={action.href}>{content}</Link>
            </Button>
        );
    }

    return (
        <Button
            type={action.form ? 'submit' : 'button'}
            form={action.form}
            onClick={action.onClick}
            disabled={action.disabled}
            {...props}
        >
            {content}
        </Button>
    );
}
