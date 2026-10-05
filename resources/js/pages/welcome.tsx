import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    ClipboardCheck,
    Eye,
    FileWarning,
    GraduationCap,
    KeyRound,
    ListChecks,
    ShieldAlert,
} from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

// The linked chain from PLAN.md: each record feeds the next.
const CHAIN = [
    'Observation',
    'Incident',
    'Investigation',
    'Corrective action',
    'Risk review',
    'Toolbox talk',
];

const MODULES = [
    {
        icon: Eye,
        title: 'Observations & near misses',
        text: 'Report an unsafe act or condition from a phone in under a minute, with a photo and location. Anonymous if needed.',
    },
    {
        icon: FileWarning,
        title: 'Incidents & investigations',
        text: 'Injuries, spills and damage, classified from the treatment given. 5-Why root cause, and an injury log ready to print.',
    },
    {
        icon: ListChecks,
        title: 'Corrective actions',
        text: 'One action register for every source. Nothing closes until someone other than the owner has verified the fix.',
    },
    {
        icon: ShieldAlert,
        title: 'Risk assessments',
        text: 'HIRA and JSA on a 5×5 matrix with residual risk. An incident flags the assessment it should have prevented for review.',
    },
    {
        icon: ClipboardCheck,
        title: 'Inspections & audits',
        text: 'Checklist inspections where a failed item raises an action on the spot, plus ISO 45001 internal audits.',
    },
    {
        icon: KeyRound,
        title: 'Permit to work',
        text: 'Hot work, confined space, work at height and electrical permits, with LOTO isolations and gas tests.',
    },
    {
        icon: GraduationCap,
        title: 'Training & competency',
        text: 'A competency matrix, where an expired ticket blocks the permit. Toolbox talks with attendee sign-on.',
    },
    {
        icon: Activity,
        title: 'KPIs',
        text: 'LTIFR, TRIR, days since the last lost-time injury and the safety pyramid, per site.',
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Health, safety & environment" />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
                    <div className="flex items-center gap-2 font-semibold">
                        <span className="flex size-8 items-center justify-center rounded-md bg-green-700">
                            <AppLogoIcon className="size-5 fill-current text-white" />
                        </span>
                        {name}
                    </div>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild>
                                    <Link href={login()}>Log in</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto max-w-6xl px-4 sm:px-6">
                    <section className="py-16 sm:py-24">
                        <p className="text-sm font-medium text-green-700 dark:text-green-500">
                            Health, safety & environment management · ISO 45001
                        </p>
                        <h1 className="mt-3 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            Every near miss, followed through to the fix.
                        </h1>
                        <p className="mt-5 max-w-2xl text-lg text-pretty text-muted-foreground">
                            Report hazards from the field, investigate
                            incidents, control high-risk work and track every
                            corrective action until it is verified. One linked
                            record, from the first report to the updated risk
                            assessment.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Button
                                size="lg"
                                className="bg-green-700 text-white hover:bg-green-800"
                                asChild
                            >
                                <Link href={auth.user ? dashboard() : login()}>
                                    {auth.user
                                        ? 'Go to dashboard'
                                        : 'Log in to report'}
                                    <ArrowRight />
                                </Link>
                            </Button>
                        </div>

                        <ol className="mt-14 flex flex-wrap items-center gap-2 text-sm">
                            {CHAIN.map((step, i) => (
                                <li
                                    key={step}
                                    className="flex items-center gap-2"
                                >
                                    <span className="rounded-full border bg-card px-3 py-1">
                                        {step}
                                    </span>
                                    {i < CHAIN.length - 1 && (
                                        <ArrowRight
                                            aria-hidden
                                            className="size-4 text-muted-foreground"
                                        />
                                    )}
                                </li>
                            ))}
                        </ol>
                    </section>

                    <section
                        aria-label="Modules"
                        className="grid gap-4 pb-20 sm:grid-cols-2 lg:grid-cols-4"
                    >
                        {MODULES.map(({ icon: Icon, title, text }) => (
                            <div
                                key={title}
                                className="rounded-xl border bg-card p-5"
                            >
                                <Icon className="size-5 text-green-700 dark:text-green-500" />
                                <h2 className="mt-3 font-medium">{title}</h2>
                                <p className="mt-1.5 text-sm text-muted-foreground">
                                    {text}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto max-w-6xl px-4 py-6 text-sm text-muted-foreground sm:px-6">
                        {name} · Health, safety & environment management
                    </div>
                </footer>
            </div>
        </>
    );
}
