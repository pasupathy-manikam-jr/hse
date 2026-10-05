import { Head, router, useForm } from '@inertiajs/react';
import { FileText, FlaskConical, Plus, SquarePen, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { GHS_HAZARDS, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import chemicalRoutes from '@/routes/chemicals';
import type { Paginated, TableFilters } from '@/types';

type Chemical = {
    id: number;
    site_id: number;
    area_id: number | null;
    name: string;
    supplier: string | null;
    product_code: string | null;
    hazards: string[] | null;
    max_quantity: string | null;
    unit: string | null;
    sds_issued_on: string | null;
    risk_assessment_id: number | null;
    file_name: string | null;
    needs_coshh: boolean;
    sds_outdated: boolean;
    site: { id: number; code: string };
    area: { id: number; name: string } | null;
    risk_assessment: { id: number; number: string; status: string } | null;
};

type Site = {
    id: number;
    code: string;
    name: string;
    areas: { id: number; name: string }[];
};

const blank = {
    site_id: '',
    area_id: '',
    name: '',
    supplier: '',
    product_code: '',
    hazards: [] as string[],
    max_quantity: '',
    unit: '',
    sds_issued_on: '',
    risk_assessment_id: '',
    sds: null as File | null,
};

export default function Chemicals({
    chemicals,
    sites,
    assessments,
    filters,
}: {
    chemicals: Paginated<Chemical>;
    sites: Site[];
    assessments: {
        id: number;
        site_id: number;
        number: string;
        title: string;
    }[];
    hazards: string[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = chemicalRoutes.index();
    const [editing, setEditing] = useState<Chemical | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Chemical | null>(null);
    const form = useForm(blank);
    const site = sites.find((s) => String(s.id) === form.data.site_id);

    const openForm = (c: Chemical | null) => {
        form.clearErrors();
        form.setData(
            c
                ? {
                      site_id: String(c.site_id),
                      area_id: String(c.area_id ?? ''),
                      name: c.name,
                      supplier: c.supplier ?? '',
                      product_code: c.product_code ?? '',
                      hazards: c.hazards ?? [],
                      max_quantity:
                          c.max_quantity === null
                              ? ''
                              : String(Number(c.max_quantity)),
                      unit: c.unit ?? '',
                      sds_issued_on: c.sds_issued_on ?? '',
                      risk_assessment_id: String(c.risk_assessment_id ?? ''),
                      sds: null,
                  }
                : {
                      ...blank,
                      site_id: String(sites.length === 1 ? sites[0].id : ''),
                  },
        );
        setEditing(c ?? 'new');
    };

    const columns: Column<Chemical>[] = [
        {
            key: 'name',
            label: 'Chemical',
            sortable: true,
            className: 'min-w-56',
            render: (c) => (
                <div>
                    <div className="font-medium">{c.name}</div>
                    <div className="text-muted-foreground">
                        {[c.supplier, c.product_code]
                            .filter(Boolean)
                            .join(' · ')}
                    </div>
                    <div className="text-muted-foreground">
                        {c.site.code}
                        {c.area && ` · ${c.area.name}`}
                        {c.max_quantity &&
                            ` · ${t('max :q :u', {
                                q: Number(c.max_quantity),
                                u: c.unit ?? '',
                            })}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'hazards',
            label: 'Hazards',
            render: (c) => (
                <div className="flex max-w-80 flex-wrap gap-1">
                    {(c.hazards ?? []).map((h) => (
                        <Badge key={h} variant="outline">
                            {t(labelOf(GHS_HAZARDS, h))}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'sds_issued_on',
            label: 'SDS',
            sortable: true,
            render: (c) => (
                <div className="grid justify-items-start gap-1">
                    {c.file_name ? (
                        <a
                            href={chemicalRoutes.sds.url(c.id)}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                        >
                            <FileText className="size-4" />
                            <DateCell value={c.sds_issued_on} />
                        </a>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    )}
                    {c.sds_outdated && (
                        <StatusBadge
                            status="pending"
                            label={t(
                                c.file_name ? 'SDS over 5 years' : 'No SDS',
                            )}
                        />
                    )}
                </div>
            ),
        },
        {
            key: 'coshh',
            label: 'COSHH',
            render: (c) =>
                c.risk_assessment ? (
                    <span>{c.risk_assessment.number}</span>
                ) : c.needs_coshh ? (
                    <StatusBadge status="high" label={t('Assessment needed')} />
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <>
            <Head title={t('Chemical register')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Chemical register"
                    description="Hazardous substances on site, their safety data sheets, and the COSHH assessment covering their use."
                    action={
                        can('create-chemicals') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add chemical')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={chemicals}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="hazard"
                                label="Any hazard"
                                options={GHS_HAZARDS.map((h) => ({
                                    id: h.value,
                                    name: t(h.label),
                                }))}
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
                    actions={(c) => (
                        <>
                            {can('edit-chemicals') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(c)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-chemicals') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(c)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
                title={editing === 'new' ? 'Add chemical' : 'Edit chemical'}
                description="Health hazards need an approved COSHH assessment; keep the SDS current."
                icon={FlaskConical}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(
                        editing === 'new' || editing === null
                            ? chemicalRoutes.store.url()
                            : chemicalRoutes.update.url(editing.id),
                        {
                            forceFormData: true,
                            preserveScroll: true,
                            onSuccess: () => setEditing(null),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="chem-name">
                            {t('Product name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="chem-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="chem-supplier">{t('Supplier')}</Label>
                        <Input
                            id="chem-supplier"
                            value={form.data.supplier}
                            onChange={(e) =>
                                form.setData('supplier', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="chem-code">{t('Product code')}</Label>
                        <Input
                            id="chem-code"
                            value={form.data.product_code}
                            onChange={(e) =>
                                form.setData('product_code', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label id="chem-hazards-label">
                            {t('Hazard pictograms')}
                        </Label>
                        <ToggleGroup
                            type="multiple"
                            variant="outline"
                            aria-labelledby="chem-hazards-label"
                            value={form.data.hazards}
                            onValueChange={(v) => form.setData('hazards', v)}
                            className="flex flex-wrap justify-start gap-1.5"
                        >
                            {GHS_HAZARDS.map((h) => (
                                <ToggleGroupItem
                                    key={h.value}
                                    value={h.value}
                                    size="sm"
                                    className="rounded-full border px-3 first:rounded-full last:rounded-full data-[state=on]:border-green-700 data-[state=on]:bg-green-50 data-[state=on]:text-green-800 data-[variant=outline]:border-l dark:data-[state=on]:bg-green-950 dark:data-[state=on]:text-green-200"
                                >
                                    {t(h.label)}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <InputError message={form.errors.hazards} />
                    </div>
                    {editing === 'new' && sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="chem-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="chem-site"
                                value={form.data.site_id}
                                onChange={(e) =>
                                    form.setData((d) => ({
                                        ...d,
                                        site_id: e.target.value,
                                        area_id: '',
                                        risk_assessment_id: '',
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
                        <Label htmlFor="chem-area">{t('Store / area')}</Label>
                        <SelectField
                            id="chem-area"
                            value={form.data.area_id}
                            onChange={(e) =>
                                form.setData('area_id', e.target.value)
                            }
                            disabled={!site}
                        >
                            <option value="">{t('Not specified')}</option>
                            {site?.areas.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                    <div className="grid grid-cols-[1fr_6rem] gap-2">
                        <div className="grid gap-2">
                            <Label htmlFor="chem-qty">
                                {t('Max quantity')}
                            </Label>
                            <Input
                                id="chem-qty"
                                inputMode="decimal"
                                value={form.data.max_quantity}
                                onChange={(e) =>
                                    form.setData('max_quantity', e.target.value)
                                }
                            />
                            <InputError message={form.errors.max_quantity} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="chem-unit">{t('Unit')}</Label>
                            <Input
                                id="chem-unit"
                                placeholder="L"
                                value={form.data.unit}
                                onChange={(e) =>
                                    form.setData('unit', e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="chem-coshh">
                            {t('COSHH assessment')}
                        </Label>
                        <SelectField
                            id="chem-coshh"
                            value={form.data.risk_assessment_id}
                            onChange={(e) =>
                                form.setData(
                                    'risk_assessment_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('None yet')}</option>
                            {assessments
                                .filter(
                                    (a) =>
                                        String(a.site_id) === form.data.site_id,
                                )
                                .map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {a.number} · {a.title}
                                    </option>
                                ))}
                        </SelectField>
                        <InputError message={form.errors.risk_assessment_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="chem-sds-date">
                            {t('SDS issue date')}
                        </Label>
                        <DatePicker
                            id="chem-sds-date"
                            value={form.data.sds_issued_on}
                            onChange={(v) => form.setData('sds_issued_on', v)}
                        />
                        <InputError message={form.errors.sds_issued_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="chem-sds">
                            {t('Safety data sheet (PDF)')}
                        </Label>
                        <Input
                            id="chem-sds"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) =>
                                form.setData('sds', e.target.files?.[0] ?? null)
                            }
                        />
                        <InputError message={form.errors.sds} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Remove chemical"
                description="It is removed from the register with its SDS."
                confirmLabel="Remove"
                onConfirm={() =>
                    deleting &&
                    router.delete(chemicalRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Chemicals.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Chemical register', href: chemicalRoutes.index() },
    ],
};
