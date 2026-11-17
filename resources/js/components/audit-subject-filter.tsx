import { useState } from 'react';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslate } from '@/lib/i18n';

const ALL = 'all';

/** Record type plus an optional record number, e.g. Deposit #12. */
export function AuditSubjectFilter({
    subjects,
    subject,
    subjectId,
    onChange,
}: {
    subjects: { value: string; label: string }[];
    subject: string | null;
    subjectId: number | null;
    onChange: (subject: string | null, subjectId: number | null) => void;
}) {
    const __ = useTranslate();
    const [number, setNumber] = useState(subjectId ? String(subjectId) : '');
    const commit = () => {
        const parsed = Number.parseInt(number, 10);

        onChange(
            subject,
            Number.isInteger(parsed) && parsed > 0 ? parsed : null,
        );
    };

    return (
        <>
            <div className="flex flex-col gap-1">
                <Label htmlFor="audit-subject">{__('Record')}</Label>
                <Combobox
                    id="audit-subject"
                    value={subject ?? ALL}
                    onValueChange={(next) => {
                        setNumber('');
                        onChange(next === ALL ? null : next, null);
                    }}
                    options={[
                        { value: ALL, label: __('All records') },
                        ...subjects,
                    ]}
                    searchPlaceholder={__('Search')}
                    emptyText={__('Nothing found.')}
                    className="md:w-52"
                />
            </div>
            {subject && (
                <div className="flex flex-col gap-1">
                    <Label htmlFor="audit-subject-id">{__('Number')}</Label>
                    <Input
                        id="audit-subject-id"
                        inputMode="numeric"
                        pattern="\d*"
                        value={number}
                        placeholder={__('Any')}
                        onChange={(event) => setNumber(event.target.value)}
                        onBlur={commit}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                commit();
                            }
                        }}
                        className="md:w-28"
                    />
                </div>
            )}
        </>
    );
}
