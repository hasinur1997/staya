import { Head, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { DashboardTemplate } from '@/components/templates/dashboard-template';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslate } from '@/lib/i18n';
import { dashboard, upcoming } from '@/routes';

type Props = {
    feature: {
        path: string;
        title: string;
        description: string;
        highlights: string[];
    };
};

export default function Upcoming({ feature }: Props) {
    const __ = useTranslate();

    setLayoutProps({
        breadcrumbs: [{ title: feature.title, href: upcoming(feature.path) }],
    });

    return (
        <>
            <Head title={feature.title} />
            <DashboardTemplate
                header={{
                    title: feature.title,
                    description: feature.description,
                    status: (
                        <Badge variant="warning">{__('Coming soon')}</Badge>
                    ),
                    secondaryActions: [
                        {
                            label: __('Back to dashboard'),
                            href: dashboard(),
                            icon: ArrowLeft,
                        },
                    ],
                }}
            >
                <Card>
                    <CardHeader>
                        <CardTitle>{__('What’s on the way')}</CardTitle>
                        <CardDescription>
                            {__(
                                'This screen is still being built. Here is what it will let you do.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="flex list-disc flex-col gap-2 pl-6 text-body text-ink">
                            {feature.highlights.map((highlight) => (
                                <li key={highlight}>{highlight}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </DashboardTemplate>
        </>
    );
}
