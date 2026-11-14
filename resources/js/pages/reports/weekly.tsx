import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/page-header';
import { ScoreBadge } from '@/components/score-badge';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import {
    CLASSIFICATIONS,
    INCIDENT_TYPES,
    OBSERVATION_TYPES,
    labelOf,
} from '@/lib/hse';
import { dashboard } from '@/routes';
import reportRoutes from '@/routes/reports';

type Props = {
    week: { from: string; to: string; number: number; year: number };
    siteId: number | null;
    sites: { id: number; code: string; name: string }[];
    observations: Record<string, number>;
    incidents: {
        id: number;
        number: string;
        type: string;
        title: string;
        occurred_at: string;
        classification: string;
        status: string;
        site: { id: number; code: string };
    }[];
    prevention: {
        inspections: number;
        inspection_score: number | string | null;
        toolbox_talks: number;
        attendees: number;
        permits: number;
    };
    actions: {
        raised: number;
        verified: number;
        open: number;
        overdue: {
            id: number;
            number: string;
            description: string;
            due_on: string;
            owner: { id: number; name: string } | null;
        }[];
    };
    equipmentOverdue: {
        id: number;
        asset_tag: string;
        name: string;
        next_inspection_on: string | null;
        site: { id: number; code: string };
    }[];
    daysSinceLti: { site: string; days: number | null }[];
};

/** Shift a Y-m-d date by whole days, in local time. */
const shift = (day: string, days: number) => {
    const [y, m, d] = day.split('-').map(Number);
    const next = new Date(y, m - 1, d + days);

    return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`;
};

function Tile({ label, value }: { label: string; value: ReactNode }) {
    const { t } = useTranslation();

    return (
        <div className="rounded-xl border p-4">
            <div className="text-sm text-muted-foreground">{t(label)}</div>
            <div className="mt-1 text-2xl font-semibold tabular-nums">
                {value}
            </div>
        </div>
    );
}

export default function WeeklyReport({
    week,
    siteId,
    sites,
    observations,
    incidents,
    prevention,
    actions,
    equipmentOverdue,
    daysSinceLti,
}: Props) {
    const { t } = useTranslation();
    const { date, dateTime } = useFormat();
    const site = sites.find((s) => s.id === siteId);
    const totalObservations = Object.values(observations).reduce(
        (a, b) => a + Number(b),
        0,
    );
    const go = (changes: { week?: string; site_id?: string }) =>
        router.get(
            reportRoutes.weekly.url({
                query: {
                    week: changes.week ?? week.from,
                    site_id: (changes.site_id ?? siteId) || undefined,
                },
            }),
            {},
            { preserveScroll: true },
        );
    const score = prevention.inspection_score;

    return (
        <>
            <Head title={t('Weekly HSE report')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6 print:p-0">
                <div className="print:hidden">
                    <PageHeader
                        title="Weekly HSE report"
                        description="What was reported, what prevention work was done, and what is still open. Print it for the weekly meeting."
                        action={
                            <Button onClick={() => window.print()}>
                                <Printer /> {t('Print')}
                            </Button>
                        }
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2 print:hidden">
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label={t('Previous week')}
                        onClick={() => go({ week: shift(week.from, -7) })}
                    >
                        <ChevronLeft />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label={t('Next week')}
                        onClick={() => go({ week: shift(week.from, 7) })}
                    >
                        <ChevronRight />
                    </Button>
                    {sites.length > 1 && (
                        <SelectField
                            aria-label={t('Site')}
                            className="w-auto min-w-56"
                            value={String(siteId ?? '')}
                            onChange={(e) => go({ site_id: e.target.value })}
                        >
                            <option value="">{t('All my sites')}</option>
                            {sites.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.code} · {s.name}
                                </option>
                            ))}
                        </SelectField>
                    )}
                </div>

                <div>
                    <h2 className="text-lg font-semibold">
                        {t('Week :n, :y', { n: week.number, y: week.year })}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {date(week.from)} – {date(week.to)} ·{' '}
                        {site
                            ? `${site.code} · ${site.name}`
                            : t('All my sites')}
                    </p>
                </div>

                <section className="grid gap-3">
                    <h3 className="font-medium">{t('Prevention')}</h3>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                        <Tile label="Observations" value={totalObservations} />
                        <Tile
                            label="Inspections"
                            value={
                                <span>
                                    {prevention.inspections}
                                    {score !== null && (
                                        <span className="ms-2 text-base">
                                            <ScoreBadge
                                                score={Math.round(
                                                    Number(score),
                                                )}
                                            />
                                        </span>
                                    )}
                                </span>
                            }
                        />
                        <Tile
                            label="Toolbox talks"
                            value={
                                <span>
                                    {prevention.toolbox_talks}
                                    <span className="ms-2 text-sm font-normal text-muted-foreground">
                                        {t(':n attended', {
                                            n: prevention.attendees,
                                        })}
                                    </span>
                                </span>
                            }
                        />
                        <Tile
                            label="Permits approved"
                            value={prevention.permits}
                        />
                        <Tile
                            label="Actions verified"
                            value={actions.verified}
                        />
                    </div>
                    {totalObservations > 0 && (
                        <ul className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                            {OBSERVATION_TYPES.filter(
                                (o) => observations[o.value],
                            ).map((o) => (
                                <li key={o.value}>
                                    <span className="text-muted-foreground">
                                        {t(o.label)}:
                                    </span>{' '}
                                    <span className="font-medium tabular-nums">
                                        {observations[o.value]}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="grid gap-3">
                    <h3 className="font-medium">
                        {t('Incidents (:n)', { n: incidents.length })}
                    </h3>
                    {incidents.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No incidents this week.')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border print:overflow-visible">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            {t('No.')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('When')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('What happened')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('Classification')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('Status')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {incidents.map((i) => (
                                        <tr key={i.id}>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {i.number}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {dateTime(i.occurred_at)}
                                            </td>
                                            <td className="px-3 py-2">
                                                <div>{i.title}</div>
                                                <div className="text-muted-foreground">
                                                    {i.site.code} ·{' '}
                                                    {t(
                                                        labelOf(
                                                            INCIDENT_TYPES,
                                                            i.type,
                                                        ),
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-3 py-2">
                                                {t(
                                                    labelOf(
                                                        CLASSIFICATIONS,
                                                        i.classification,
                                                    ),
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                <StatusBadge
                                                    status={i.status}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="grid gap-3">
                    <h3 className="font-medium">{t('Still open')}</h3>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Tile label="Actions raised" value={actions.raised} />
                        <Tile label="Actions open" value={actions.open} />
                        <Tile
                            label="Actions overdue"
                            value={actions.overdue.length}
                        />
                        <Tile
                            label="Equipment overdue"
                            value={equipmentOverdue.length}
                        />
                    </div>
                    {actions.overdue.length > 0 && (
                        <ul className="divide-y rounded-xl border text-sm">
                            {actions.overdue.map((a) => (
                                <li
                                    key={a.id}
                                    className="flex flex-wrap justify-between gap-2 px-3 py-2"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {a.number}
                                        </span>{' '}
                                        {a.description}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {a.owner?.name ?? '—'} ·{' '}
                                        {t('due :d', { d: date(a.due_on) })}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {equipmentOverdue.length > 0 && (
                        <ul className="divide-y rounded-xl border text-sm">
                            {equipmentOverdue.map((e) => (
                                <li
                                    key={e.id}
                                    className="flex flex-wrap justify-between gap-2 px-3 py-2"
                                >
                                    <span>
                                        <span className="font-medium">
                                            {e.asset_tag}
                                        </span>{' '}
                                        {e.name} · {e.site.code}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {e.next_inspection_on
                                            ? t('inspection due :d', {
                                                  d: date(e.next_inspection_on),
                                              })
                                            : t('never inspected')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="grid gap-3">
                    <h3 className="font-medium">
                        {t('Days since the last lost-time injury (today)')}
                    </h3>
                    <ul className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        {daysSinceLti.map((d) => (
                            <li key={d.site}>
                                <span className="text-muted-foreground">
                                    {d.site}:
                                </span>{' '}
                                <span className="font-medium tabular-nums">
                                    {d.days ?? t('none recorded')}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

WeeklyReport.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Weekly HSE report', href: reportRoutes.weekly() },
    ],
};
