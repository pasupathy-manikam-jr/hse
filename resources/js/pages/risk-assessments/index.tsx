import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, Plus, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { RISK_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import riskRoutes from '@/routes/risk-assessments';
import type { Paginated, RiskAssessment, TableFilters } from '@/types';

type Row = RiskAssessment & { hazards_count: number };
type Site = {
    id: number;
    code: string;
    name: string;
    areas: { id: number; name: string }[];
};

// Calendar dates compare as local days (the review is due at the end of that day).
const overdue = (a: RiskAssessment) =>
    a.status === 'approved' &&
    new Date(`${a.review_due_on}T23:59:59`) < new Date();

export default function RiskAssessments({
    assessments,
    counts,
    sites,
    filters,
}: {
    assessments: Paginated<Row>;
    counts: Record<string, number>;
    sites: Site[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = riskRoutes.index();
    const [creating, setCreating] = useState(false);
    const form = useForm({
        type: 'hira',
        title: '',
        site_id: String(sites.length === 1 ? sites[0].id : ''),
        area_id: '',
        activity: '',
        review_due_on: '',
    });
    const areas =
        sites.find((s) => String(s.id) === form.data.site_id)?.areas ?? [];

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (a) => (
                <div className="grid justify-items-start gap-1">
                    <IdBadge>{a.number}</IdBadge>
                    <span className="text-xs text-muted-foreground">
                        {t('Rev :r', { r: a.revision })}
                    </span>
                </div>
            ),
        },
        {
            key: 'title',
            label: 'Assessment',
            className: 'min-w-64',
            render: (a) => (
                <div>
                    <div className="font-medium">{a.title}</div>
                    <div className="text-muted-foreground">
                        {t(labelOf(RISK_TYPES, a.type))} · {a.site.code}
                        {a.area && ` · ${a.area.name}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'hazards',
            label: 'Hazards',
            render: (a) => a.hazards_count,
        },
        {
            key: 'review_due_on',
            label: 'Review due',
            sortable: true,
            render: (a) => (
                <div className="grid justify-items-start gap-1">
                    <DateCell value={a.review_due_on} />
                    {a.review_required ? (
                        <StatusBadge
                            status="review-required"
                            label={t('Review required')}
                        />
                    ) : (
                        overdue(a) && <StatusBadge status="overdue" />
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
            <Head title={t('Risk assessments')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Risk assessments"
                    description="Hazards of each activity, scored on a 5×5 matrix before and after controls. Approved revisions are the ones in force."
                    action={
                        can('create-risk-assessments') && (
                            <Button
                                onClick={() => {
                                    form.clearErrors();
                                    setCreating(true);
                                }}
                            >
                                <Plus /> {t('New assessment')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={assessments}
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
                                name="type"
                                label="All types"
                                options={RISK_TYPES.map((r) => ({
                                    id: r.value,
                                    name: t(r.label),
                                }))}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="review"
                                label="Any review state"
                                options={[
                                    {
                                        id: 'required',
                                        name: t('Needs review'),
                                    },
                                ]}
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
                    actions={(a) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={riskRoutes.show(a.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>

            <FormDialog
                open={creating}
                onOpenChange={setCreating}
                title="New risk assessment"
                description="Describe the activity; you add and score the hazards on the next page."
                icon={ShieldAlert}
                submitLabel="Create"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(riskRoutes.store.url());
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ra-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="ra-title"
                            placeholder={t('e.g. Erecting scaffold, Block A')}
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ra-type">{t('Type')}</Label>
                        <SelectField
                            id="ra-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            {RISK_TYPES.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {t(r.label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ra-review">
                            {t('Review by')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="ra-review"
                            value={form.data.review_due_on}
                            onChange={(value) =>
                                form.setData('review_due_on', value)
                            }
                        />
                        <InputError message={form.errors.review_due_on} />
                    </div>
                    {sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="ra-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="ra-site"
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
                        <Label htmlFor="ra-area">{t('Area')}</Label>
                        <SelectField
                            id="ra-area"
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
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ra-activity">
                            {t('Activity')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="ra-activity"
                            rows={3}
                            placeholder={t(
                                'The work, its steps, and the equipment used',
                            )}
                            value={form.data.activity}
                            onChange={(e) =>
                                form.setData('activity', e.target.value)
                            }
                        />
                        <InputError message={form.errors.activity} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

RiskAssessments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Risk assessments', href: riskRoutes.index() },
    ],
};
