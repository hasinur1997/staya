import { Head, Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CircleCheck,
    ChartColumn,
    ClipboardCheck,
    Utensils,
    UserPlus,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LanguageMenu } from '@/components/language-menu';
import { Button } from '@/components/ui/button';
import { SignedMoney } from '@/components/signed-money';
import { useTranslate } from '@/lib/i18n';
import { formatMoney } from '@/lib/money';
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

const highlights = [
    'Deposits are approved before they count',
    'Fund balance always equals the ledger',
    'Every change is logged and reviewable',
];

const sampleMembers = [
    { name: 'Rahim', balance: '1250.00' },
    { name: 'Karim', balance: '-800.00' },
    { name: 'Sumon', balance: '0.00' },
];

const steps = [
    {
        icon: UserPlus,
        title: 'Add your members',
        text: 'Invite everyone in the mess and give each person the access they need.',
    },
    {
        icon: ClipboardCheck,
        title: 'Record as you go',
        text: 'Log meals, grocery purchases and deposits through the month.',
    },
    {
        icon: ChartColumn,
        title: 'Close the month',
        text: 'See each member’s due or credit and share one report everyone can trust.',
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
                <header className="sticky top-0 z-10 border-b bg-background/90 backdrop-blur">
                    <div className="mx-auto flex w-full max-w-content items-center justify-between gap-4 px-4 py-4 md:px-8">
                        <Link href="/" className="flex items-center gap-3">
                            <AppLogo />
                        </Link>
                        <nav className="flex items-center gap-2">
                            <LanguageMenu />
                            {auth.user ? (
                                <Button asChild>
                                    <Link href={dashboard()}>
                                        {__('Dashboard')}
                                    </Link>
                                </Button>
                            ) : (
                                <>
                                    <Button variant="ghost" asChild>
                                        <Link href={login()}>
                                            {__('Log in')}
                                        </Link>
                                    </Button>
                                    <Button asChild>
                                        <Link href={register()}>
                                            {__('Register')}
                                        </Link>
                                    </Button>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="flex-1">
                    <section className="border-b bg-surface">
                        <div className="mx-auto grid w-full max-w-content items-center gap-12 px-4 py-16 md:px-8 md:py-24 lg:grid-cols-2">
                            <div className="flex flex-col items-start gap-6">
                                <p className="text-primary-text text-overline uppercase">
                                    {name}
                                </p>
                                <h1 className="text-display font-bold tracking-[-0.02em] text-ink md:text-5xl md:leading-tight">
                                    {__('Run your mess with clear numbers.')}
                                </h1>
                                <p className="max-w-xl text-body text-ink-muted md:text-lg">
                                    {__(
                                        'Members, meals, grocery purchases and deposits in one place, with monthly reports everyone can trust.',
                                    )}
                                </p>
                                <ul className="flex flex-col gap-3">
                                    {highlights.map((item) => (
                                        <li
                                            key={item}
                                            className="flex items-center gap-3 text-body"
                                        >
                                            <CircleCheck
                                                className="size-5 shrink-0 text-success"
                                                aria-hidden
                                            />
                                            {__(item)}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div
                                role="img"
                                aria-label={__('Sample monthly summary')}
                                className="flex flex-col gap-4 rounded-lg border bg-background p-6 shadow-card"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-title">
                                        {__('Monthly summary')}
                                    </p>
                                    <span className="text-primary-text rounded-sm bg-primary-soft px-2 py-1 text-caption">
                                        {__('Sample')}
                                    </span>
                                </div>
                                <div className="flex flex-col gap-1 border-b pb-4">
                                    <p className="text-caption text-ink-muted">
                                        {__('Fund balance')}
                                    </p>
                                    <p className="money text-money-lg">
                                        {formatMoney('48250')}
                                    </p>
                                </div>
                                <dl className="grid grid-cols-2 gap-4 border-b pb-4">
                                    <div className="flex flex-col gap-1">
                                        <dt className="text-caption text-ink-muted">
                                            {__('Deposits')}
                                        </dt>
                                        <dd className="money text-body-strong">
                                            {formatMoney('62000')}
                                        </dd>
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <dt className="text-caption text-ink-muted">
                                            {__('Grocery')}
                                        </dt>
                                        <dd className="money text-body-strong">
                                            {formatMoney('13750')}
                                        </dd>
                                    </div>
                                </dl>
                                <ul className="flex flex-col gap-3">
                                    {sampleMembers.map((member) => (
                                        <li
                                            key={member.name}
                                            className="flex items-center justify-between gap-4"
                                        >
                                            <span className="text-body">
                                                {member.name}
                                            </span>
                                            <span className="flex items-center gap-3">
                                                <SignedMoney
                                                    value={member.balance}
                                                    positiveLabel={__('Credit')}
                                                    negativeLabel={__('Due')}
                                                />
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section
                        aria-label={__('What you can do')}
                        className="mx-auto w-full max-w-content px-4 py-16 md:px-8 md:py-20"
                    >
                        <div className="mb-10 flex max-w-2xl flex-col gap-2">
                            <p className="text-primary-text text-overline uppercase">
                                {__('Features')}
                            </p>
                            <h2 className="text-heading font-bold text-ink md:text-3xl">
                                {__('Everything your mess needs, in one place')}
                            </h2>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="flex flex-col gap-3 rounded-lg border bg-background p-6 shadow-card"
                                >
                                    <span className="flex size-12 items-center justify-center rounded-md bg-primary-soft text-primary">
                                        <feature.icon
                                            className="size-6"
                                            aria-hidden
                                        />
                                    </span>
                                    <h3 className="text-title">
                                        {__(feature.title)}
                                    </h3>
                                    <p className="text-caption text-ink-muted">
                                        {__(feature.text)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="border-y bg-surface">
                        <div className="mx-auto w-full max-w-content px-4 py-16 md:px-8 md:py-20">
                            <div className="mb-10 flex max-w-2xl flex-col gap-2">
                                <p className="text-primary-text text-overline uppercase">
                                    {__('How it works')}
                                </p>
                                <h2 className="text-heading font-bold text-ink md:text-3xl">
                                    {__(
                                        'From the first meal to the monthly report',
                                    )}
                                </h2>
                            </div>
                            <ol className="grid gap-6 md:grid-cols-3">
                                {steps.map((step, index) => (
                                    <li
                                        key={step.title}
                                        className="flex flex-col gap-3 rounded-lg border bg-background p-6"
                                    >
                                        <span className="flex items-center gap-3">
                                            <span className="flex size-8 items-center justify-center rounded-pill bg-primary text-label text-on-primary tabular-nums">
                                                {index + 1}
                                            </span>
                                            <step.icon
                                                className="size-5 text-ink-muted"
                                                aria-hidden
                                            />
                                        </span>
                                        <h3 className="text-title">
                                            {__(step.title)}
                                        </h3>
                                        <p className="text-caption text-ink-muted">
                                            {__(step.text)}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex w-full max-w-content flex-col items-center justify-between gap-2 px-4 py-6 md:flex-row md:px-8">
                        <p className="text-caption text-ink-muted">
                            © {new Date().getFullYear()} Staya · {name}
                        </p>
                        <p className="text-caption text-ink-muted">
                            {__('Every taka accounted for.')}
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
