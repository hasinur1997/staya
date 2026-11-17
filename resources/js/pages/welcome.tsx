import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, ChartColumn, Utensils, Wallet } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useTranslate } from '@/lib/i18n';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        icon: Wallet,
        title: 'Deposits and one shared fund',
        text: 'Members submit deposits, managers approve them, and the fund balance always equals the ledger.',
    },
    {
        icon: Utensils,
        title: 'Meals and grocery',
        text: 'Record meals by day, log purchases with receipts, and see the cost of every meal.',
    },
    {
        icon: ChartColumn,
        title: 'Reports at a glance',
        text: 'Monthly dues and credits, charts and closed months, ready to review.',
    },
    {
        icon: BookOpen,
        title: 'An auditable record',
        text: 'Every change is logged with who made it and what it was before.',
    },
];

/** The public front page, built from the same tokens and type scale as the app. */
export default function Welcome() {
    const { auth, name } = usePage().props;
    const __ = useTranslate();

    return (
        <>
            <Head title={__('Welcome')} />
            <div className="flex min-h-svh flex-col bg-background text-ink">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-6 md:px-6">
                    <Link href="/" className="flex items-center gap-3">
                        <AppLogo />
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>
                                    {__('Dashboard')}
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>{__('Log in')}</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>
                                        {__('Register')}
                                    </Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-12 px-4 pt-8 pb-16 md:gap-16 md:px-6 md:pt-16">
                    <section className="flex max-w-2xl flex-col gap-4">
                        <p className="text-overline text-neutral">{name}</p>
                        <h1 className="text-heading md:text-headline">
                            {__('Run your mess with clear numbers.')}
                        </h1>
                        <p className="text-body text-ink-muted">
                            {__(
                                'Members, meals, grocery purchases and deposits in one place, with monthly reports everyone can trust.',
                            )}
                        </p>
                        {!auth.user && (
                            <div className="flex flex-wrap gap-2 pt-2">
                                <Button size="lg" asChild>
                                    <Link href={login()}>{__('Log in')}</Link>
                                </Button>
                                <Button size="lg" variant="outline" asChild>
                                    <Link href={register()}>
                                        {__('Register')}
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </section>

                    <section
                        aria-label={__('What you can do')}
                        className="grid gap-4 md:grid-cols-2 md:gap-6"
                    >
                        {features.map((feature) => (
                            <Card key={feature.title} className="gap-2 px-4">
                                <feature.icon
                                    className="size-6 text-primary"
                                    aria-hidden
                                />
                                <h2 className="text-subhead">
                                    {__(feature.title)}
                                </h2>
                                <p className="text-small text-ink-muted">
                                    {__(feature.text)}
                                </p>
                            </Card>
                        ))}
                    </section>
                </main>
            </div>
        </>
    );
}
