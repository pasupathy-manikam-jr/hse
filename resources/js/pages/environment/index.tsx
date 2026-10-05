import { Head, router, useForm } from '@inertiajs/react';
import { Leaf, Recycle } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import environmentRoutes from '@/routes/environment';

type Metric = { key: string; label: string; unit: string };
type Site = { id: number; code: string; name: string };

const number = new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 });

const monthLabel = (ym: string) =>
    new Date(`${ym}-01T00:00:00`).toLocaleDateString('en-GB', {
        month: 'short',
    });

export default function Environment({
    sites,
    site,
    year,
    months,
    metrics,
    values,
    totals,
    spills,
    recyclingRate,
}: {
    sites: Site[];
    site: Site | null;
    year: number;
    months: string[];
    metrics: Metric[];
    values: Record<string, number>;
    totals: Record<string, number>;
    spills: Record<string, number>;
    recyclingRate: number | null;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const editable = can('edit-environment');
    const thisMonth = new Date().toISOString().slice(0, 7);
    const [cell, setCell] = useState<{ metric: Metric; month: string } | null>(
        null,
    );
    const form = useForm({ site_id: '', month: '', metric: '', value: '' });
    const go = (changes: {
        site_id?: number | string;
        year?: number | string;
    }) =>
        router.get(
            environmentRoutes.index.url({
                query: {
                    site_id: changes.site_id ?? site?.id,
                    year: changes.year ?? year,
                },
            }),
            {},
            { preserveScroll: true },
        );

    const open = (metric: Metric, month: string) => {
        form.clearErrors();
        form.setData({
            site_id: String(site?.id ?? ''),
            month,
            metric: metric.key,
            value: String(values[`${metric.key}|${month}`] ?? ''),
        });
        setCell({ metric, month });
    };

    return (
        <>
            <Head title={t('Environment')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Environmental log"
                    description="Monthly waste, water, energy and fuel per site. Spills are environmental-release incidents."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {sites.length > 1 && (
                                <SelectField
                                    aria-label={t('Site')}
                                    className="w-auto min-w-40"
                                    value={String(site?.id ?? '')}
                                    onChange={(e) =>
                                        go({ site_id: e.target.value })
                                    }
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
                                {Array.from(
                                    { length: 5 },
                                    (_, n) => new Date().getFullYear() - n,
                                ).map((y) => (
                                    <option key={y} value={y}>
                                        {y}
                                    </option>
                                ))}
                            </SelectField>
                        </div>
                    }
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="flex items-center gap-3 rounded-xl border p-4">
                        <Recycle className="size-6 text-green-700 dark:text-green-500" />
                        <div className="grid">
                            <span className="text-2xl font-semibold tabular-nums">
                                {recyclingRate === null
                                    ? '—'
                                    : `${recyclingRate}%`}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {t('of waste recycled in :y', { y: year })}
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 rounded-xl border p-4">
                        <Leaf className="size-6 text-green-700 dark:text-green-500" />
                        <div className="grid">
                            <span className="text-2xl font-semibold tabular-nums">
                                {Object.values(spills).reduce(
                                    (a, b) => a + b,
                                    0,
                                )}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {t('spills or releases in :y', { y: year })}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="sticky start-0 bg-muted/50 px-3 py-2 font-medium">
                                    {t('Metric')}
                                </th>
                                {months.map((m) => (
                                    <th
                                        key={m}
                                        className="px-2 py-2 text-end font-medium"
                                    >
                                        {monthLabel(m)}
                                    </th>
                                ))}
                                <th className="px-3 py-2 text-end font-medium">
                                    {t('Total')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {metrics.map((metric) => (
                                <tr key={metric.key}>
                                    <th
                                        scope="row"
                                        className="sticky start-0 bg-background px-3 py-2 text-left font-medium whitespace-nowrap"
                                    >
                                        {t(metric.label)}{' '}
                                        <span className="font-normal text-muted-foreground">
                                            ({metric.unit})
                                        </span>
                                    </th>
                                    {months.map((m) => {
                                        const value =
                                            values[`${metric.key}|${m}`];
                                        const future = m > thisMonth;

                                        return (
                                            <td key={m} className="p-0.5">
                                                <button
                                                    type="button"
                                                    disabled={
                                                        !editable || future
                                                    }
                                                    aria-label={t(
                                                        ':metric, :month',
                                                        {
                                                            metric: metric.label,
                                                            month: m,
                                                        },
                                                    )}
                                                    onClick={() =>
                                                        open(metric, m)
                                                    }
                                                    className="h-9 w-full min-w-16 rounded-md px-2 text-end tabular-nums enabled:hover:bg-muted disabled:text-muted-foreground"
                                                >
                                                    {value === undefined
                                                        ? '—'
                                                        : number.format(value)}
                                                </button>
                                            </td>
                                        );
                                    })}
                                    <td className="px-3 py-2 text-end font-semibold tabular-nums">
                                        {number.format(totals[metric.key] ?? 0)}
                                    </td>
                                </tr>
                            ))}
                            <tr>
                                <th
                                    scope="row"
                                    className="sticky start-0 bg-background px-3 py-2 text-left font-medium"
                                >
                                    {t('Spills / releases')}
                                </th>
                                {months.map((m) => (
                                    <td
                                        key={m}
                                        className="px-2 py-2 text-end tabular-nums"
                                    >
                                        {spills[m] ?? 0}
                                    </td>
                                ))}
                                <td className="px-3 py-2 text-end font-semibold tabular-nums">
                                    {Object.values(spills).reduce(
                                        (a, b) => a + b,
                                        0,
                                    )}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <FormDialog
                open={cell !== null}
                onOpenChange={(open) => !open && setCell(null)}
                title={cell ? cell.metric.label : 'Record figure'}
                description={
                    cell
                        ? t(':site, :month: total for the month in :unit.', {
                              site: site?.code ?? '',
                              month: cell.month,
                              unit: cell.metric.unit,
                          })
                        : ''
                }
                icon={Leaf}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(environmentRoutes.store.url(), {
                        preserveScroll: true,
                        onSuccess: () => setCell(null),
                    });
                }}
                processing={form.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="env-value">
                        {t('Amount (:unit)', { unit: cell?.metric.unit ?? '' })}
                    </Label>
                    <Input
                        id="env-value"
                        inputMode="decimal"
                        value={form.data.value}
                        onChange={(e) => form.setData('value', e.target.value)}
                    />
                    <InputError
                        message={form.errors.value ?? form.errors.month}
                    />
                </div>
            </FormDialog>
        </>
    );
}

Environment.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Environment', href: environmentRoutes.index() },
    ],
};
