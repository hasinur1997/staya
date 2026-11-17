import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ResponsiveTable } from '@/components/responsive-table';
import type { ResponsiveColumn } from '@/components/responsive-table';
import { DetailTemplate } from '@/components/templates/detail-template';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';
import { index } from '@/routes/meals';
import { index as typesIndex, store, update } from '@/routes/meal-types';
import type { MealType } from '@/types';

type Props = {
    types: MealType[];
    can: { manage: boolean };
    maxPerType: number;
};

export default function MealTypes({ types, can, maxPerType }: Props) {
    const __ = useTranslate();
    const [editing, setEditing] = useState<MealType | 'new' | null>(null);

    setLayoutProps({
        breadcrumbs: [
            { title: __('Meals'), href: index() },
            { title: __('Meal types'), href: typesIndex() },
        ],
    });

    const columns: ResponsiveColumn<MealType>[] = [
        { key: 'name', header: __('Name'), cell: (type) => type.name },
        {
            key: 'order',
            header: __('Order'),
            align: 'right',
            cell: (type) => String(type.sort_order),
        },
        {
            key: 'status',
            header: __('Status'),
            cell: (type) => (
                <Badge variant={type.is_active ? 'success' : 'neutral'}>
                    {type.is_active ? __('In use') : __('Switched off')}
                </Badge>
            ),
        },
        {
            key: 'actions',
            header: __('Actions'),
            align: 'right',
            cell: (type) =>
                can.manage ? (
                    <span className="relative flex justify-end">
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setEditing(type)}
                        >
                            {__('Edit')}
                        </Button>
                    </span>
                ) : null,
        },
    ];

    const current = editing === 'new' ? null : editing;

    return (
        <>
            <Head title={__('Meal types')} />
            <DetailTemplate
                header={{
                    title: __('Meal types'),
                    description: __(
                        'The kinds of meal served in a day, such as breakfast, lunch or dinner. Switch a type off to stop recording it; past meals are kept.',
                    ),
                    primaryAction: can.manage
                        ? {
                              label: __('Add meal type'),
                              icon: Plus,
                              onClick: () => setEditing('new'),
                          }
                        : undefined,
                }}
            >
                <ResponsiveTable
                    caption={__('Meal types')}
                    columns={columns}
                    rows={types}
                    rowKey={(type) => type.id}
                    card={(type) => ({
                        title: type.name,
                        status: (
                            <Badge
                                variant={type.is_active ? 'success' : 'neutral'}
                            >
                                {type.is_active
                                    ? __('In use')
                                    : __('Switched off')}
                            </Badge>
                        ),
                        meta: [__('Order :n', { n: type.sort_order })],
                    })}
                />
            </DetailTemplate>

            {editing !== null && (
                <Dialog open onOpenChange={(open) => !open && setEditing(null)}>
                    <DialogContent>
                        <DialogTitle>
                            {current
                                ? __('Edit meal type')
                                : __('Add meal type')}
                        </DialogTitle>
                        <DialogDescription>
                            {__(
                                'A member can have up to :max meals of one type in a day.',
                                { max: maxPerType },
                            )}
                        </DialogDescription>
                        <Form
                            action={current ? update(current.id) : store()}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="flex flex-col gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="type-name">
                                            {__('Name')}
                                        </Label>
                                        <Input
                                            id="type-name"
                                            name="name"
                                            defaultValue={current?.name ?? ''}
                                            required
                                            maxLength={50}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="type-order">
                                            {__('Order on the entry screen')}
                                        </Label>
                                        <Input
                                            id="type-order"
                                            name="sort_order"
                                            type="number"
                                            min={0}
                                            max={999}
                                            defaultValue={
                                                current?.sort_order ??
                                                (types.at(-1)?.sort_order ??
                                                    0) + 1
                                            }
                                            className="md:w-32"
                                            required
                                        />
                                        <InputError
                                            message={errors.sort_order}
                                        />
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value="0"
                                        />
                                        <Checkbox
                                            id="type-active"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={
                                                current?.is_active ?? true
                                            }
                                        />
                                        <Label htmlFor="type-active">
                                            {__('In use')}
                                        </Label>
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                            >
                                                {__('Cancel')}
                                            </Button>
                                        </DialogClose>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {__('Save')}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
