import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, Gauge, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { ScoreBadge } from '@/components/score-badge';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import gapRoutes from '@/routes/gap-analyses';
import type { Paginated, TableFilters } from '@/types';

type Row = {
    id: number;
    number: string;
    title: string;
    assessed_on: string;
    status: string;
    score: number | null;
    gaps_count: number;
    site: { id: number; code: string };
    creator: { id: number; name: string } | null;
};

export default function GapAnalyses({
    analyses,
    counts,
    sites,
    filters,
}: {
    analyses: Paginated<Row>;
    counts: Record<string, number>;
    sites: { id: number; code: string; name: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = gapRoutes.index();
    const [starting, setStarting] = useState(false);
    const form = useForm({
        site_id: String(sites.length === 1 ? sites[0].id : ''),
        title: '',
        assessed_on: '',
    });

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (g) => <IdBadge>{g.number}</IdBadge>,
        },
        {
            key: 'title',
            label: 'Gap analysis',
            className: 'min-w-64',
            render: (g) => (
                <div>
                    <div className="font-medium">{g.title}</div>
                    <div className="text-muted-foreground">
                        {g.site.code}
                        {g.creator && ` · ${g.creator.name}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'assessed_on',
            label: 'Assessed',
            sortable: true,
            render: (g) => <DateCell value={g.assessed_on} />,
        },
        {
            key: 'score',
            label: 'Score',
            render: (g) => (
                <div className="text-sm">
                    <ScoreBadge score={g.score} />
                    {g.gaps_count > 0 && (
                        <div className="text-xs text-red-700 dark:text-red-400">
                            {t(':n gaps', { n: g.gaps_count })}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (g) => <StatusBadge status={g.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Gap analysis')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="ISO 45001 gap analysis"
                    description="Rate each site against every clause, raise actions for the gaps, and compare with the last analysis."
                    action={
                        can('create-audits') && (
                            <Button
                                onClick={() => {
                                    form.clearErrors();
                                    setStarting(true);
                                }}
                            >
                                <Plus /> {t('Start gap analysis')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={analyses}
                    columns={columns}
                    filters={filters}
                    url={url}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={counts}
                        />
                    }
                    toolbar={
                        sites.length > 1 && (
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="site_id"
                                label="All sites"
                                options={sites.map((s) => ({
                                    id: s.id,
                                    name: s.code,
                                }))}
                            />
                        )
                    }
                    actions={(g) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={gapRoutes.show(g.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>

            <FormDialog
                open={starting}
                onOpenChange={setStarting}
                title="Start gap analysis"
                description="Every ISO 45001 clause is listed for you to rate."
                icon={Gauge}
                submitLabel="Start"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(gapRoutes.store.url());
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="gap-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="gap-title"
                            placeholder={t(
                                'e.g. Pre-certification gap analysis',
                            )}
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    {sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="gap-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="gap-site"
                                value={form.data.site_id}
                                onChange={(e) =>
                                    form.setData('site_id', e.target.value)
                                }
                            >
                                <option value="">{t('Select site')}</option>
                                {sites.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.code} · {s.name}
                                    </option>
                                ))}
                            </SelectField>
                            <InputError message={form.errors.site_id} />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="gap-date">
                            {t('Assessed on')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="gap-date"
                            value={form.data.assessed_on}
                            onChange={(v) => form.setData('assessed_on', v)}
                        />
                        <InputError message={form.errors.assessed_on} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

GapAnalyses.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Gap analysis', href: gapRoutes.index() },
    ],
};
