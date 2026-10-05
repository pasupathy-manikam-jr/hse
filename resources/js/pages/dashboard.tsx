import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    BookOpenCheck,
    CalendarClock,
    Clock,
    FileClock,
    KeyRound,
    ListChecks,
    Repeat,
    ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import actionRoutes from '@/routes/actions';
import dashboardRoutes from '@/routes/dashboard';
import documentRoutes from '@/routes/documents';
import handoverRoutes from '@/routes/handovers';
import permitRoutes from '@/routes/permits';
import riskRoutes from '@/routes/risk-assessments';

type Lagging = {
    hours: number;
    lti: number;
    recordable: number;
    ltifr: number | null;
    trir: number | null;
    pyramid: Record<string, number>;
};

type Leading = {
    observations_by_month: { month: string; count: number }[];
    inspections: number;
    inspection_score: number | null;
    toolbox_talks: number;
    actions_open: number;
    actions_overdue: number;
};

type Site = { id: number; code: string; name: string };

// The single series' bar colour (validated: light green-700, dark green-600).
const BAR = 'bg-green-700 dark:bg-green-600';

const PYRAMID = [
    ['near-miss', 'Near misses'],
    ['first-aid', 'First aid'],
    ['medical', 'Medical treatment'],
    ['restricted', 'Restricted work'],
    ['lost-time', 'Lost time'],
    ['fatality', 'Fatality'],
] as const;

const TASKS: Record<
    string,
    { label: string; icon: typeof ListChecks; href: () => string }
> = {
    my_actions: {
        label: 'Actions for you',
        icon: ListChecks,
        href: () => actionRoutes.index.url({ query: { owner: 'me' } }),
    },
    to_verify: {
        label: 'Actions to verify',
        icon: BadgeCheck,
        href: () => actionRoutes.index.url({ query: { status: 'done' } }),
    },
    permits_to_approve: {
        label: 'Permits to approve',
        icon: KeyRound,
        href: () => permitRoutes.index.url({ query: { status: 'requested' } }),
    },
    assessments_to_review: {
        label: 'Assessments to approve or review',
        icon: ShieldAlert,
        href: () => riskRoutes.index.url(),
    },
    documents_to_read: {
        label: 'Documents to read',
        icon: BookOpenCheck,
        href: () => documentRoutes.reading.url(),
    },
    handovers_to_acknowledge: {
        label: 'Shift handovers to acknowledge',
        icon: Repeat,
        href: () => handoverRoutes.index.url(),
    },
    documents_to_review: {
        label: 'Your documents due for review',
        icon: FileClock,
        href: () => documentRoutes.index.url({ query: { review: 'due' } }),
    },
};

function Tile({
    label,
    value,
    caption,
    tone,
}: {
    label: string;
    value: ReactNode;
    caption?: ReactNode;
    tone?: 'critical';
}) {
    return (
        <div className="grid content-start gap-1 rounded-xl border p-4">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span
                className={cn(
                    'text-3xl font-semibold tabular-nums',
                    tone === 'critical' && 'text-red-700 dark:text-red-400',
                )}
            >
                {value}
            </span>
            {caption && (
                <span className="text-xs text-muted-foreground">{caption}</span>
            )}
        </div>
    );
}

const number = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 });

// "2026-10" → "Oct"
const monthLabel = (ym: string) =>
    new Date(`${ym}-01T00:00:00`).toLocaleDateString('en-GB', {
        month: 'short',
    });

const monthOptions = () =>
    Array.from({ length: 12 }, (_, n) => {
        const d = new Date();
        d.setDate(1);
        d.setMonth(d.getMonth() - n);

        return {
            value: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
            label: d.toLocaleDateString('en-GB', {
                month: 'long',
                year: 'numeric',
            }),
        };
    });

export default function Dashboard({
    period,
    siteId,
    sites,
    lagging,
    daysSinceLti,
    leading,
    tasks,
    hours,
}: {
    period: { key: '12m' | 'ytd'; from: string; to: string };
    siteId: number | null;
    sites: Site[];
    lagging: Lagging;
    daysSinceLti: { site: string; days: number | null }[];
    leading: Leading;
    tasks: Record<string, number>;
    hours: { id: number; site_id: number; month: string; hours: string }[];
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const [enteringHours, setEnteringHours] = useState(false);
    const hoursForm = useForm({
        site_id: String(siteId ?? (sites.length === 1 ? sites[0].id : '')),
        month: monthOptions()[1].value,
        hours: '',
    });
    const go = (changes: { period?: string; site_id?: string }) =>
        router.get(
            dashboard.url({
                query: {
                    period: changes.period ?? period.key,
                    site_id: (changes.site_id ?? siteId) || undefined,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    const pyramidMax = Math.max(1, ...Object.values(lagging.pyramid));
    const monthMax = Math.max(
        1,
        ...leading.observations_by_month.map((m) => m.count),
    );
    const noHours = lagging.hours === 0;

    return (
        <>
            <Head title={t('Dashboard')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description={t(':from to :to', {
                        from: date(period.from),
                        to: date(period.to),
                    })}
                    action={
                        <div className="flex flex-wrap items-center gap-2">
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                aria-label={t('Period')}
                                value={period.key}
                                onValueChange={(v) => v && go({ period: v })}
                            >
                                <ToggleGroupItem value="12m" className="px-3">
                                    {t('Last 12 months')}
                                </ToggleGroupItem>
                                <ToggleGroupItem value="ytd" className="px-3">
                                    {t('Year to date')}
                                </ToggleGroupItem>
                            </ToggleGroup>
                            {sites.length > 1 && (
                                <SelectField
                                    aria-label={t('Site')}
                                    className="w-auto min-w-40"
                                    value={String(siteId ?? '')}
                                    onChange={(e) =>
                                        go({ site_id: e.target.value })
                                    }
                                >
                                    <option value="">{t('All sites')}</option>
                                    {sites.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.code}
                                        </option>
                                    ))}
                                </SelectField>
                            )}
                            {can('edit-sites') && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        hoursForm.clearErrors();
                                        setEnteringHours(true);
                                    }}
                                >
                                    <Clock /> {t('Hours worked')}
                                </Button>
                            )}
                        </div>
                    }
                />

                {Object.keys(tasks).length > 0 && (
                    <section aria-label={t('Needs you')} className="grid gap-3">
                        <h2 className="font-medium">{t('Needs you')}</h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {Object.entries(tasks).map(([key, count]) => {
                                const task = TASKS[key];

                                return (
                                    <Link
                                        key={key}
                                        href={task.href()}
                                        className="flex items-center gap-3 rounded-xl border p-4 hover:bg-muted/50"
                                    >
                                        <span className="flex size-10 items-center justify-center rounded-lg bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-400">
                                            <task.icon className="size-5" />
                                        </span>
                                        <span className="grid">
                                            <span className="text-2xl font-semibold tabular-nums">
                                                {count}
                                            </span>
                                            <span className="text-sm text-muted-foreground">
                                                {t(task.label)}
                                            </span>
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                )}

                <section aria-label={t('Injury rates')} className="grid gap-3">
                    <h2 className="font-medium">{t('Injuries')}</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <Tile
                            label={t('LTIFR')}
                            value={
                                lagging.ltifr === null
                                    ? '—'
                                    : number.format(lagging.ltifr)
                            }
                            caption={t('Lost-time injuries per million hours')}
                        />
                        <Tile
                            label={t('TRIR')}
                            value={
                                lagging.trir === null
                                    ? '—'
                                    : number.format(lagging.trir)
                            }
                            caption={t('Recordable injuries per 200,000 hours')}
                        />
                        <Tile
                            label={t('Lost-time injuries')}
                            value={lagging.lti}
                            tone={lagging.lti > 0 ? 'critical' : undefined}
                        />
                        <Tile
                            label={t('Recordable injuries')}
                            value={lagging.recordable}
                        />
                        <Tile
                            label={t('Hours worked')}
                            value={number.format(lagging.hours)}
                            caption={
                                noHours
                                    ? t('Enter monthly hours to see the rates.')
                                    : undefined
                            }
                        />
                    </div>
                    {daysSinceLti.length > 0 && (
                        <div className="flex flex-wrap gap-3">
                            {daysSinceLti.map((d) => (
                                <div
                                    key={d.site}
                                    className="flex items-center gap-3 rounded-xl border px-4 py-3"
                                >
                                    <CalendarClock className="size-5 text-muted-foreground" />
                                    <span className="text-sm">
                                        <span className="font-semibold tabular-nums">
                                            {d.days === null ? '—' : d.days}
                                        </span>{' '}
                                        {d.days === null
                                            ? t(
                                                  ':site: no lost-time injury recorded',
                                                  {
                                                      site: d.site,
                                                  },
                                              )
                                            : t(
                                                  'days since the last lost-time injury at :site',
                                                  { site: d.site },
                                              )}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="grid content-start gap-3 rounded-xl border p-5">
                        <div>
                            <h2 className="font-medium">
                                {t('Safety pyramid')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'Near misses and injuries by severity in the period. Plenty of near misses reported is a good sign.',
                                )}
                            </p>
                        </div>
                        <ul className="grid gap-2" aria-hidden>
                            {PYRAMID.map(([key, label]) => {
                                const value = lagging.pyramid[key] ?? 0;

                                return (
                                    <li
                                        key={key}
                                        title={`${t(label)}: ${value}`}
                                        className="grid grid-cols-[9rem_1fr_2.5rem] items-center gap-3 text-sm"
                                    >
                                        <span className="text-muted-foreground">
                                            {t(label)}
                                        </span>
                                        <span className="h-5">
                                            {value > 0 && (
                                                <span
                                                    className={cn(
                                                        'block h-full rounded-e-[4px]',
                                                        BAR,
                                                    )}
                                                    style={{
                                                        width: `${Math.max(2, (100 * value) / pyramidMax)}%`,
                                                    }}
                                                />
                                            )}
                                        </span>
                                        <span className="text-end font-medium tabular-nums">
                                            {value}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                        <table className="sr-only">
                            <caption>{t('Safety pyramid')}</caption>
                            <tbody>
                                {PYRAMID.map(([key, label]) => (
                                    <tr key={key}>
                                        <th scope="row">{t(label)}</th>
                                        <td>{lagging.pyramid[key] ?? 0}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>

                    <section className="grid content-start gap-3 rounded-xl border p-5">
                        <div>
                            <h2 className="font-medium">
                                {t('Observations reported')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('Per month, last 6 months.')}
                            </p>
                        </div>
                        <div
                            className="grid h-44 grid-cols-6 items-end gap-2 border-b"
                            aria-hidden
                        >
                            {leading.observations_by_month.map((m) => (
                                <div
                                    key={m.month}
                                    title={`${monthLabel(m.month)}: ${m.count}`}
                                    className="flex h-full flex-col items-center justify-end gap-1"
                                >
                                    <span className="text-xs font-medium tabular-nums">
                                        {m.count}
                                    </span>
                                    <span
                                        className={cn(
                                            'w-full max-w-10 rounded-t-[4px]',
                                            BAR,
                                        )}
                                        style={{
                                            height: `${(100 * m.count) / monthMax}%`,
                                        }}
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="grid grid-cols-6 gap-2 text-center text-xs text-muted-foreground">
                            {leading.observations_by_month.map((m) => (
                                <span key={m.month}>{monthLabel(m.month)}</span>
                            ))}
                        </div>
                        <table className="sr-only">
                            <caption>
                                {t('Observations reported per month')}
                            </caption>
                            <tbody>
                                {leading.observations_by_month.map((m) => (
                                    <tr key={m.month}>
                                        <th scope="row">{m.month}</th>
                                        <td>{m.count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                </div>

                <section
                    aria-label={t('Leading indicators')}
                    className="grid gap-3"
                >
                    <h2 className="font-medium">{t('Prevention')}</h2>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Tile
                            label={t('Inspections completed')}
                            value={leading.inspections}
                            caption={
                                leading.inspection_score === null
                                    ? undefined
                                    : t('Average score :s%', {
                                          s: leading.inspection_score,
                                      })
                            }
                        />
                        <Tile
                            label={t('Toolbox talks')}
                            value={leading.toolbox_talks}
                        />
                        <Tile
                            label={t('Open actions')}
                            value={leading.actions_open}
                        />
                        <Tile
                            label={t('Overdue actions')}
                            value={leading.actions_overdue}
                            tone={
                                leading.actions_overdue > 0
                                    ? 'critical'
                                    : undefined
                            }
                        />
                    </div>
                </section>
            </div>

            <FormDialog
                open={enteringHours}
                onOpenChange={setEnteringHours}
                title="Hours worked"
                description="Total hours worked at the site in the month, staff and contractors. Saving again for the same month corrects it."
                icon={Clock}
                onSubmit={(e) => {
                    e.preventDefault();
                    hoursForm.post(dashboardRoutes.hours.url(), {
                        preserveScroll: true,
                        onSuccess: () => hoursForm.setData('hours', ''),
                    });
                }}
                processing={hoursForm.processing}
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    {sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="hrs-site">{t('Site')}</Label>
                            <SelectField
                                id="hrs-site"
                                value={hoursForm.data.site_id}
                                onChange={(e) =>
                                    hoursForm.setData('site_id', e.target.value)
                                }
                            >
                                <option value="">{t('Select site')}</option>
                                {sites.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.code}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={hoursForm.errors.site_id} />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="hrs-month">{t('Month')}</Label>
                        <SelectField
                            id="hrs-month"
                            value={hoursForm.data.month}
                            onChange={(e) =>
                                hoursForm.setData('month', e.target.value)
                            }
                        >
                            {monthOptions().map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={hoursForm.errors.month} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="hrs-hours">{t('Hours')}</Label>
                        <Input
                            id="hrs-hours"
                            inputMode="decimal"
                            value={hoursForm.data.hours}
                            onChange={(e) =>
                                hoursForm.setData('hours', e.target.value)
                            }
                        />
                        <InputError message={hoursForm.errors.hours} />
                    </div>
                </div>
                {hours.length > 0 && (
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="py-1 font-normal">
                                    {t('Site')}
                                </th>
                                <th className="py-1 font-normal">
                                    {t('Month')}
                                </th>
                                <th className="py-1 text-end font-normal">
                                    {t('Hours')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {hours.map((h) => (
                                <tr key={h.id}>
                                    <td className="py-1">
                                        {sites.find((s) => s.id === h.site_id)
                                            ?.code ?? '—'}
                                    </td>
                                    <td className="py-1">{h.month}</td>
                                    <td className="py-1 text-end tabular-nums">
                                        {number.format(Number(h.hours))}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </FormDialog>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
