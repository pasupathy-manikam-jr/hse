import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { ILLNESS_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import incidentRoutes from '@/routes/incidents';

type Case = {
    incident_id: number;
    number: string;
    date: string;
    name: string;
    job_title: string | null;
    where: string;
    description: string;
    death: boolean;
    serious: boolean;
    days_lost: number;
    lost_workday_case: boolean;
    illness_type: string | null;
    reported: boolean;
};

type Occurrence = {
    incident_id: number;
    number: string;
    date: string;
    code: string;
    title: string;
    reported: boolean;
};

type Summary = {
    accident_cases: number;
    accident_deaths: number;
    accident_lost_workday_cases: number;
    accident_days_lost: number;
    disease_cases: number;
    disease_deaths: number;
    disease_days_lost: number;
    dangerous_occurrences: number;
    man_hours: number | null;
    average_workers: number | null;
    months_with_hours: number;
    incident_rate: number | null;
    fatality_rate: number | null;
    frequency_rate: number | null;
    severity_rate: number | null;
    due_on: string;
};

type Site = { id: number; code: string; name: string };

const number = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 });

export default function Jkkp8Register({
    sites,
    site,
    year,
    accidents,
    occurrences,
    diseases,
    summary,
    dangerousOccurrences,
}: {
    sites: Site[];
    site: Site | null;
    year: number;
    accidents: Case[];
    occurrences: Occurrence[];
    diseases: Case[];
    summary: Summary | null;
    dangerousOccurrences: Record<string, string>;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const thisYear = new Date().getFullYear();
    const go = (changes: { site_id?: string; year?: string }) =>
        router.get(
            incidentRoutes.register.url({
                query: {
                    site_id: changes.site_id ?? site?.id,
                    year: changes.year ?? year,
                },
            }),
            {},
            { preserveScroll: true },
        );
    const rate = (value: number | null) =>
        value === null ? '—' : number.format(value);

    return (
        <>
            <Head title={t('JKKP 8 register')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6 print:p-0">
                <div className="print:hidden">
                    <PageHeader
                        title="JKKP 8 register"
                        description="The register of accidents, dangerous occurrences, occupational poisonings and diseases (NADOPOD 2004). Send the year's summary to DOSH HQ by 31 January and keep it for 5 years."
                        action={
                            <div className="flex flex-wrap gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={incidentRoutes.index()}>
                                        <ArrowLeft /> {t('Back')}
                                    </Link>
                                </Button>
                                <Button onClick={() => window.print()}>
                                    <Printer /> {t('Print')}
                                </Button>
                            </div>
                        }
                    />
                </div>

                <div className="flex flex-wrap gap-2 print:hidden">
                    {sites.length > 1 && (
                        <SelectField
                            aria-label={t('Site')}
                            className="w-auto min-w-56"
                            value={String(site?.id ?? '')}
                            onChange={(e) => go({ site_id: e.target.value })}
                        >
                            {sites.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.code} · {s.name}
                                </option>
                            ))}
                        </SelectField>
                    )}
                    <SelectField
                        aria-label={t('Year')}
                        className="w-auto min-w-28"
                        value={String(year)}
                        onChange={(e) => go({ year: e.target.value })}
                    >
                        {Array.from({ length: 5 }, (_, n) => thisYear - n).map(
                            (y) => (
                                <option key={y} value={y}>
                                    {y}
                                </option>
                            ),
                        )}
                    </SelectField>
                </div>

                <div>
                    <h2 className="text-lg font-semibold">
                        {t(
                            'Register of accident, dangerous occurrence, occupational poisoning and occupational disease',
                        )}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {site ? `${site.code} · ${site.name}` : '—'} ·{' '}
                        {t('Calendar year :y', { y: year })}
                        {summary &&
                            ` · ${t('Summary due :d', { d: date(summary.due_on) })}`}
                    </p>
                </div>

                <CaseTable
                    title="Accidents"
                    empty="No accident cases this year."
                    cases={accidents}
                />

                <section className="grid gap-2">
                    <h3 className="font-medium">
                        {t('Dangerous occurrences')}
                    </h3>
                    {occurrences.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No dangerous occurrences this year.')}
                        </p>
                    ) : (
                        <Table
                            head={[
                                'Incident',
                                'Date',
                                'Code',
                                'What happened',
                                'JKKP 6',
                            ]}
                            rows={occurrences.map((o) => [
                                <IncidentLink key="n" id={o.incident_id}>
                                    {o.number}
                                </IncidentLink>,
                                date(o.date),
                                <span
                                    key="c"
                                    title={t(
                                        dangerousOccurrences[o.code] ?? '',
                                    )}
                                >
                                    {o.code}
                                </span>,
                                o.title,
                                o.reported ? t('Sent') : t('Not yet'),
                            ])}
                        />
                    )}
                </section>

                <CaseTable
                    title="Occupational poisoning and disease"
                    empty="No occupational poisoning or disease cases this year."
                    cases={diseases}
                    disease
                />

                {summary && (
                    <section className="grid break-inside-avoid gap-3">
                        <h3 className="font-medium">{t('Summary')}</h3>
                        <div className="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
                            {(
                                [
                                    ['Accident cases', summary.accident_cases],
                                    [
                                        'Deaths (accidents)',
                                        summary.accident_deaths,
                                    ],
                                    [
                                        'Lost workday cases',
                                        summary.accident_lost_workday_cases,
                                    ],
                                    [
                                        'Days lost (a death counts 6,000)',
                                        summary.accident_days_lost,
                                    ],
                                    [
                                        'Poisoning and disease cases',
                                        summary.disease_cases,
                                    ],
                                    [
                                        'Deaths (poisoning and disease)',
                                        summary.disease_deaths,
                                    ],
                                    [
                                        'Days lost (poisoning and disease)',
                                        summary.disease_days_lost,
                                    ],
                                    [
                                        'Dangerous occurrences',
                                        summary.dangerous_occurrences,
                                    ],
                                    [
                                        'Man-hours worked',
                                        summary.man_hours === null
                                            ? t('not entered')
                                            : `${number.format(summary.man_hours)} (${t(':n months', { n: summary.months_with_hours })})`,
                                    ],
                                    [
                                        'Average workers',
                                        summary.average_workers === null
                                            ? t('not entered')
                                            : number.format(
                                                  summary.average_workers,
                                              ),
                                    ],
                                ] as [string, ReactNode][]
                            ).map(([label, value]) => (
                                <div
                                    key={label}
                                    className="flex justify-between gap-4 border-b py-1"
                                >
                                    <span className="text-muted-foreground">
                                        {t(label)}
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {value}
                                    </span>
                                </div>
                            ))}
                        </div>
                        <Table
                            head={['Rate', 'Value', 'How it is worked out']}
                            rows={[
                                [
                                    'Incident rate',
                                    rate(summary.incident_rate),
                                    'accident cases × 1,000 ÷ average workers',
                                ],
                                [
                                    'Fatality rate',
                                    rate(summary.fatality_rate),
                                    'deaths × 1,000 ÷ average workers',
                                ],
                                [
                                    'Frequency rate',
                                    rate(summary.frequency_rate),
                                    'accident cases × 1,000,000 ÷ man-hours',
                                ],
                                [
                                    'Severity rate',
                                    rate(summary.severity_rate),
                                    'days lost × 1,000,000 ÷ man-hours',
                                ],
                            ].map(([label, value, how]) => [
                                t(label),
                                value,
                                t(how),
                            ])}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Hours worked and average workers come from the monthly figures on the dashboard. Check the formulas against the current JKKP 8 form before you submit. If there were no cases, tell DOSH instead of sending a blank form.',
                            )}
                        </p>
                    </section>
                )}
            </div>
        </>
    );
}

function IncidentLink({ id, children }: { id: number; children: ReactNode }) {
    return (
        <Link
            href={incidentRoutes.show(id)}
            className="text-blue-600 hover:underline print:text-inherit print:no-underline"
        >
            {children}
        </Link>
    );
}

function Table({ head, rows }: { head: string[]; rows: ReactNode[][] }) {
    const { t } = useTranslation();

    return (
        <div className="overflow-x-auto rounded-xl border print:overflow-visible print:rounded-none">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                    <tr className="[&>th]:px-2 [&>th]:py-2 [&>th]:font-medium">
                        {head.map((h) => (
                            <th key={h}>{t(h)}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row, n) => (
                        <tr key={n} className="[&>td]:px-2 [&>td]:py-1.5">
                            {row.map((cell, c) => (
                                <td key={c}>{cell}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CaseTable({
    title,
    empty,
    cases,
    disease = false,
}: {
    title: string;
    empty: string;
    cases: Case[];
    disease?: boolean;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();

    return (
        <section className="grid gap-2">
            <h3 className="font-medium">{t(title)}</h3>
            {cases.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t(empty)}</p>
            ) : (
                <Table
                    head={[
                        'Incident',
                        'Date',
                        'Name',
                        'Job',
                        'Where',
                        disease ? 'Poisoning / disease' : 'Injury',
                        'Death',
                        'Days lost',
                        disease ? 'JKKP 7' : 'JKKP 6',
                    ]}
                    rows={cases.map((c) => [
                        <IncidentLink key="n" id={c.incident_id}>
                            {c.number}
                        </IncidentLink>,
                        date(c.date),
                        c.name,
                        c.job_title ?? '',
                        c.where,
                        disease && c.illness_type
                            ? `${t(labelOf(ILLNESS_TYPES, c.illness_type))}${c.description ? ` — ${c.description}` : ''}`
                            : `${c.description}${c.serious ? ` (${t('serious')})` : ''}`,
                        c.death ? t('Yes') : '',
                        c.days_lost,
                        c.reported ? t('Sent') : '—',
                    ])}
                />
            )}
        </section>
    );
}

Jkkp8Register.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Incidents', href: incidentRoutes.index() },
        { title: 'JKKP 8 register', href: incidentRoutes.register() },
    ],
};
