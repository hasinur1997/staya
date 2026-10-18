import { Head, usePage } from '@inertiajs/react';
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
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { user } = usePage().props.auth;
    const __ = useTranslate();

    return (
        <>
            <Head title="Dashboard" />
            <DashboardTemplate
                header={{ title: __('Welcome, :name', { name: user.name }) }}
            >
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center gap-2">
                            <CardTitle>{__('Your monthly summary')}</CardTitle>
                            <Badge variant="warning">{__('Coming soon')}</Badge>
                        </div>
                        <CardDescription>
                            {__(
                                'Your share, deposits and due or credit for the month will appear here once deposits and grocery purchases can be recorded.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p className="text-body text-ink-muted">
                            {__(
                                'Screens marked coming soon in the sidebar are still being built.',
                            )}
                        </p>
                    </CardContent>
                </Card>
            </DashboardTemplate>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
