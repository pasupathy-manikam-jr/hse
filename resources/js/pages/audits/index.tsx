import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList, Eye, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PeoplePicker } from '@/components/people-picker';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import auditRoutes from '@/routes/audits';
import type { Paginated, TableFilters } from '@/types';

type Row = {
    id: number;
    number: string;
    title: string;
    planned_on: string;
    status: string;
    site: { id: number; code: string };
    lead_auditor: { id: number; name: string } | null;
    findings_count: number;
    nonconformities_count: number;
};

export default function Audits({
    audits,
    counts,
    sites,
    people,
    clauses,
    filters,
}: {
    audits: Paginated<Row>;
    counts: Record<string, number>;
    sites: { id: number; code: string; name: string }[];
    people: { id: number; name: string }[];
    clauses: { id: number; number: string; title: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = auditRoutes.index();
    const [planning, setPlanning] = useState(false);
    const form = useForm({
        site_id: String(sites.length === 1 ? sites[0].id : ''),
        title: '',
        scope: '',
        lead_auditor_id: '',
        planned_on: '',
        clause_ids: [] as number[],
    });

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (a) => <IdBadge>{a.number}</IdBadge>,
        },
        {
            key: 'title',
            label: 'Audit',
            className: 'min-w-64',
            render: (a) => (
                <div>
                    <div className="font-medium">{a.title}</div>
                    <div className="text-muted-foreground">
                        {a.site.code}
                        {a.lead_auditor && ` · ${a.lead_auditor.name}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'planned_on',
            label: 'Planned',
            sortable: true,
            render: (a) => <DateCell value={a.planned_on} />,
        },
        {
            key: 'findings',
            label: 'Findings',
            render: (a) => (
                <div className="text-sm">
                    <div>{a.findings_count}</div>
                    {a.nonconformities_count > 0 && (
                        <div className="text-xs text-red-700 dark:text-red-400">
                            {t(':n nonconformities', {
                                n: a.nonconformities_count,
                            })}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (a) => <StatusBadge status={a.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Audits')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Internal audits"
                    description="ISO 45001 internal audits of each site. A nonconformity raises a corrective action."
                    action={
                        can('create-audits') && (
                            <Button
                                onClick={() => {
                                    form.clearErrors();
                                    setPlanning(true);
                                }}
                            >
                                <Plus /> {t('Plan audit')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={audits}
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
                    actions={(a) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={auditRoutes.show(a.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>

            <FormDialog
                open={planning}
                onOpenChange={setPlanning}
                title="Plan audit"
                description="Where, when, who leads it, and which clauses it covers."
                icon={ClipboardList}
                submitLabel="Plan"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(auditRoutes.store.url());
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="aud-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="aud-title"
                            placeholder={t('e.g. Q4 OH&S audit: operations')}
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    {sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="aud-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="aud-site"
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
                        <Label htmlFor="aud-date">
                            {t('Planned for')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="aud-date"
                            value={form.data.planned_on}
                            onChange={(v) => form.setData('planned_on', v)}
                        />
                        <InputError message={form.errors.planned_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="aud-lead">
                            {t('Lead auditor')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="aud-lead"
                            value={form.data.lead_auditor_id}
                            onChange={(e) =>
                                form.setData('lead_auditor_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select person')}</option>
                            {people.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.lead_auditor_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="aud-scope">{t('Scope')}</Label>
                        <Textarea
                            id="aud-scope"
                            rows={2}
                            value={form.data.scope}
                            onChange={(e) =>
                                form.setData('scope', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="aud-clauses">
                            {t('Clauses audited')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <PeoplePicker
                            id="aud-clauses"
                            placeholder="Filter clauses…"
                            people={clauses.map((c) => ({
                                id: c.id,
                                name: `${c.number} ${c.title}`,
                            }))}
                            value={form.data.clause_ids}
                            onChange={(ids) => form.setData('clause_ids', ids)}
                        />
                        <InputError message={form.errors.clause_ids} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

Audits.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Audits', href: auditRoutes.index() },
    ],
};
