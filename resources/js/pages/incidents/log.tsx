import { Head, Link, router } from '@inertiajs/react';
import { Check, Printer } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { ILLNESS_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import incidentRoutes from '@/routes/incidents';

type Case = {
    case: string;
    incident_id: number;
    name: string;
    job_title: string | null;
    date: string;
    where: string;
    description: string;
    outcome: 'fatality' | 'lost-time' | 'restricted' | 'medical';
    days_away: number;
    days_restricted: number;
    category: string;
};

type Totals = {
    deaths: number;
    days_away_cases: number;
    restricted_cases: number;
    other_cases: number;
    days_away: number;
    days_restricted: number;
    injuries: number;
    'skin-disorder': number;
    respiratory: number;
    poisoning: number;
    'hearing-loss': number;
    'other-illness': number;
};

type Site = { id: number; code: string; name: string };

// OSHA 300 columns G–J: the most serious outcome of each case.
const OUTCOMES = [
    ['fatality', 'G', 'Death'],
    ['lost-time', 'H', 'Days away from work'],
    ['restricted', 'I', 'Job transfer or restriction'],
    ['medical', 'J', 'Other recordable'],
] as const;

export default function InjuryLog({
    sites,
    site,
    year,
    cases,
    totals,
}: {
    sites: Site[];
    site: Site | null;
    year: number;
    cases: Case[];
    totals: Totals;
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const thisYear = new Date().getFullYear();
    const go = (changes: { site_id?: string; year?: string }) =>
        router.get(
            incidentRoutes.log.url({
                query: {
                    site_id: changes.site_id ?? site?.id,
                    year: changes.year ?? year,
                },
            }),
            {},
            { preserveScroll: true },
        );

    const summary: [string, number][] = [
        ['Deaths (G)', totals.deaths],
        ['Cases with days away (H)', totals.days_away_cases],
        ['Cases with job transfer or restriction (I)', totals.restricted_cases],
        ['Other recordable cases (J)', totals.other_cases],
        ['Days away from work (K)', totals.days_away],
        ['Days of job transfer or restriction (L)', totals.days_restricted],
        ['Injuries (M1)', totals.injuries],
        ...ILLNESS_TYPES.map(
            (it, n) =>
                [`${it.label} (M${n + 2})`, totals[it.value]] as [
                    string,
                    number,
                ],
        ),
    ];

    return (
        <>
            <Head title={t('Injury log')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6 print:p-0">
                <div className="print:hidden">
                    <PageHeader
                        title="Injury log"
                        description="OSHA Form 300 and the 300A annual summary: one line per recordable injury or illness."
                        action={
                            <Button onClick={() => window.print()}>
                                <Printer /> {t('Print')}
                            </Button>
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

                <section className="grid gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            {t('Log of work-related injuries and illnesses')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {site ? `${site.code} · ${site.name}` : '—'} ·{' '}
                            {t('Calendar year :y', { y: year })}
                        </p>
                    </div>

                    <div className="overflow-x-auto rounded-xl border print:overflow-visible print:rounded-none">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                <tr className="[&>th]:px-2 [&>th]:py-2 [&>th]:font-medium">
                                    <th>(A) {t('Case')}</th>
                                    <th>(B) {t('Employee')}</th>
                                    <th>(C) {t('Job title')}</th>
                                    <th>(D) {t('Date')}</th>
                                    <th>(E) {t('Where')}</th>
                                    <th>(F) {t('Injury and object')}</th>
                                    {OUTCOMES.map(([, col, label]) => (
                                        <th
                                            key={col}
                                            className="text-center"
                                            title={t(label)}
                                        >
                                            ({col})
                                        </th>
                                    ))}
                                    <th className="text-end">(K)</th>
                                    <th className="text-end">(L)</th>
                                    <th>(M)</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {cases.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={13}
                                            className="px-3 py-8 text-center text-muted-foreground"
                                        >
                                            {t(
                                                'No recordable cases for this site and year.',
                                            )}
                                        </td>
                                    </tr>
                                )}
                                {cases.map((c) => (
                                    <tr
                                        key={c.case}
                                        className="align-top [&>td]:px-2 [&>td]:py-2"
                                    >
                                        <td className="whitespace-nowrap">
                                            <Link
                                                href={incidentRoutes.show(
                                                    c.incident_id,
                                                )}
                                                className="text-blue-600 hover:underline print:text-inherit print:no-underline"
                                            >
                                                {c.case}
                                            </Link>
                                        </td>
                                        <td>{c.name}</td>
                                        <td>{c.job_title}</td>
                                        <td className="whitespace-nowrap">
                                            {date(c.date)}
                                        </td>
                                        <td>{c.where}</td>
                                        <td className="min-w-48">
                                            {c.description}
                                        </td>
                                        {OUTCOMES.map(([outcome, col]) => (
                                            <td
                                                key={col}
                                                className="text-center"
                                            >
                                                {c.outcome === outcome && (
                                                    <Check
                                                        aria-label={t('Yes')}
                                                        className="mx-auto size-4"
                                                    />
                                                )}
                                            </td>
                                        ))}
                                        <td className="text-end tabular-nums">
                                            {c.days_away || ''}
                                        </td>
                                        <td className="text-end tabular-nums">
                                            {c.days_restricted || ''}
                                        </td>
                                        <td>
                                            {c.category === 'injury'
                                                ? t('Injury')
                                                : t(
                                                      labelOf(
                                                          ILLNESS_TYPES,
                                                          c.category,
                                                      ),
                                                  )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'G: death · H: days away from work · I: job transfer or restriction · J: other recordable · K/L: days, capped at 180 per case · M: injury or type of illness.',
                        )}
                    </p>
                </section>

                <section className="grid break-inside-avoid gap-3">
                    <h2 className="text-lg font-semibold">
                        {t('Summary (Form 300A)')}
                    </h2>
                    <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {summary.map(([label, value]) => (
                            <div key={label} className="rounded-xl border p-4">
                                <dt className="text-sm text-muted-foreground">
                                    {t(label)}
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold tabular-nums">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            </div>
        </>
    );
}

InjuryLog.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Incidents', href: incidentRoutes.index() },
        { title: 'Injury log', href: incidentRoutes.log() },
    ],
};
