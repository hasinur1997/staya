import { Form } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslate } from '@/lib/i18n';
import type { RouteFormDefinition } from '@/wayfinder';

type Errors = Partial<Record<string, string>>;

type Props = {
    title: string;
    description?: string;
    /** Shows the pencil icon; the server still guards the update. */
    canEdit: boolean;
    /** The form target, from Wayfinder's `.form()`. */
    form: RouteFormDefinition<'post'>;
    /** The read-only view. */
    children: ReactNode;
    /** The inputs shown while editing. */
    fields: (errors: Errors) => ReactNode;
    /** Content under the view that stays visible while editing is off. */
    footer?: ReactNode;
};

/**
 * A profile card that flips between a read-only view and a form. The pencil
 * sits in the top right corner; Save and Cancel appear only while editing.
 */
export function EditableCard({
    title,
    description,
    canEdit,
    form,
    children,
    fields,
    footer,
}: Props) {
    const __ = useTranslate();
    const [editing, setEditing] = useState(false);

    return (
        <Card className="h-full">
            <CardHeader className="flex-row items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <CardTitle className="text-title">{title}</CardTitle>
                    {description && (
                        <p className="text-caption text-ink-muted">
                            {description}
                        </p>
                    )}
                </div>
                {canEdit && !editing && (
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label={__('Edit :section', { section: title })}
                        title={__('Edit')}
                        onClick={() => setEditing(true)}
                    >
                        <Pencil />
                    </Button>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {editing ? (
                    <Form
                        noValidate
                        {...form}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setEditing(false)}
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                {fields(errors)}
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => setEditing(false)}
                                    >
                                        {__('Cancel')}
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {__('Save')}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                ) : (
                    children
                )}
                {footer}
            </CardContent>
        </Card>
    );
}
