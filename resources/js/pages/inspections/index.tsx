import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardCheck, Eye, ListChecks, Play } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { ScoreBadge } from '@/components/score-badge';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import checklistRoutes from '@/routes/checklists';
import inspectionRoutes from '@/routes/inspections';
import type { Inspection, Paginated, TableFilters } from '@/types';

type Row = Inspection & { failed_count: number };
type Site = {
    id: number;
    code: string;
    name: string;
    areas: { id: number; name: string }[];
};

export default function Inspections({
    inspections,
    counts,
    templates,
    sites,
    filters,
}: {
    inspections: Paginated<Row>;
    counts: Record<string, number>;
    templates: { id: number; name: string }[];
    sites: Site[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const url = inspectionRoutes.index();
    const [starting, setStarting] = useState(false);
    const form = useForm({
        checklist_template_id: '',
        site_id: String(sites.length === 1 ? sites[0].id : ''),
        area_id: '',
    });
    const areas =
        sites.find((s) => String(s.id) === form.data.site_id)?.areas ?? [];

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (i) => <IdBadge>{i.number}</IdBadge>,
        },
        {
            key: 'template_name',
            label: 'Checklist',
            render: (i) => (
                <div>
                    <div className="font-medium">{i.template_name}</div>
                    <div className="text-muted-foreground">
                        {i.site.code}
                        {i.area && ` · ${i.area.name}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'created_at',
            label: 'Inspected',
            sortable: true,
            render: (i) => (
                <div className="whitespace-nowrap">
                    <div>{dateTime(i.completed_at ?? i.created_at)}</div>
                    <div className="text-muted-foreground">
                        {i.creator?.name}
                    </div>
                </div>
            ),
        },
        {
            key: 'score',
            label: 'Score',
            sortable: true,
            render: (i) => (
                <div className="grid gap-0.5">
                    <ScoreBadge score={i.score} />
                    {i.failed_count > 0 && (
                        <span className="text-xs text-red-700 dark:text-red-400">
                            {t(':n failed', { n: i.failed_count })}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (i) => <StatusBadge status={i.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Inspections')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Inspections"
                    description="Checklist inspections. Every failed answer raises an action when the inspection is completed."
                    action={
                        <div className="flex flex-wrap gap-2">
                            {can('manage-checklists') && (
                                <Button variant="outline" asChild>
                                    <Link href={checklistRoutes.index()}>
                                        <ListChecks /> {t('Checklists')}
                                    </Link>
                                </Button>
                            )}
                            {can('create-inspections') && (
                                <Button
                                    onClick={() => {
                                        form.clearErrors();
                                        setStarting(true);
                                    }}
                                >
                                    <Play /> {t('Start inspection')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={inspections}
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
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="template_id"
                                label="All checklists"
                                options={templates}
                            />
                            {sites.length > 1 && (
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
                            )}
                        </>
                    }
                    actions={(i) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={inspectionRoutes.show(i.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>

            <FormDialog
                open={starting}
                onOpenChange={setStarting}
                title="Start inspection"
                description="Pick the checklist and where you are. You answer the questions on the next page."
                icon={ClipboardCheck}
                submitLabel="Start"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(inspectionRoutes.store.url());
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ins-template">
                            {t('Checklist')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="ins-template"
                            value={form.data.checklist_template_id}
                            onChange={(e) =>
                                form.setData(
                                    'checklist_template_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('Select checklist')}</option>
                            {templates.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError
                            message={form.errors.checklist_template_id}
                        />
                    </div>
                    {sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="ins-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="ins-site"
                                value={form.data.site_id}
                                onChange={(e) =>
                                    form.setData((data) => ({
                                        ...data,
                                        site_id: e.target.value,
                                        area_id: '',
                                    }))
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
                        <Label htmlFor="ins-area">{t('Area')}</Label>
                        <SelectField
                            id="ins-area"
                            value={form.data.area_id}
                            onChange={(e) =>
                                form.setData('area_id', e.target.value)
                            }
                            disabled={areas.length === 0}
                        >
                            <option value="">{t('Whole site')}</option>
                            {areas.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.area_id} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

Inspections.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Inspections', href: inspectionRoutes.index() },
    ],
};
