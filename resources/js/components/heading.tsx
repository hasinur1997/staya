import { useTranslate } from '@/lib/i18n';

export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    const __ = useTranslate();
    const Title = variant === 'small' ? 'h2' : 'h1';

    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-1'}>
            <Title
                className={
                    variant === 'small' ? 'text-subhead' : 'text-heading'
                }
            >
                {__(title)}
            </Title>
            {description && (
                <p className="text-small text-ink-muted">{__(description)}</p>
            )}
        </header>
    );
}
