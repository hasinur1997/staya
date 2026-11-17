export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    const Title = variant === 'small' ? 'h2' : 'h1';

    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-1'}>
            <Title
                className={
                    variant === 'small' ? 'text-subhead' : 'text-heading'
                }
            >
                {title}
            </Title>
            {description && (
                <p className="text-small text-ink-muted">{description}</p>
            )}
        </header>
    );
}
