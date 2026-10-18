import { Head, usePage } from '@inertiajs/react';
import { DashboardTemplate } from '@/components/templates/dashboard-template';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

export default function Dashboard() {
    const { user } = usePage().props.auth;

    return (
        <>
            <Head title="Dashboard" />
            <DashboardTemplate header={{ title: `Welcome, ${user.name}` }}>
                <Card>
                    <CardContent>
                        <p className="text-body text-ink-muted">
                            Your monthly summary appears here once deposits and
                            purchases are recorded.
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
