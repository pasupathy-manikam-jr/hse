import { Head, router, useForm } from '@inertiajs/react';
import {
    ClipboardCheck,
    FileText,
    LoaderCircle,
    Plus,
    SquarePen,
    Trash2,
    Wrench,
} from 'lucide-react';
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
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { EQUIPMENT_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import equipmentRoutes from '@/routes/equipment';
import type { Paginated, TableFilters } from '@/types';

type Equipment = {
    id: number;
    site_id: number;
    area_id: number | null;
    asset_tag: string;
    name: string;
    type: string;
    serial_number: string | null;
    owner_id: number | null;
    inspection_interval_months: number;
    last_inspected_on: string | null;
    next_inspection_on: string | null;
    status: string;
    site: { id: number; code: string };
    area: { id: number; name: string } | null;
    owner: { id: number; name: string } | null;
    inspections: Inspection[];
};

type Inspection = {
    id: number;
    inspected_on: string;
    passed: boolean;
    inspector: string;
    notes: string | null;
    file_name: string | null;
    creator: { id: number; name: string } | null;
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
    asset_tag: '',
    name: '',
    type: '',
    serial_number: '',
    owner_id: '',
    inspection_interval_months: '6',
    status: 'in-service',
};

const blankInspection = {
    inspected_on: '',
    passed: '1',
    inspector: '',
    notes: '',
    certificate: null as File | null,
};

/** Today and today + n days as local Y-m-d strings (compared as text). */
const localDay = (addDays = 0) => {
    const d = new Date();
    d.setDate(d.getDate() + addDays);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export default function EquipmentRegister({
    equipment,
    counts,
    sites,
    people,
    dueSoonDays,
    filters,
}: {
    equipment: Paginated<Equipment>;
    counts: Record<string, number>;
    sites: Site[];
    people: { id: number; name: string; site_id: number | null }[];
    dueSoonDays: number;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = equipmentRoutes.index();
    const [editing, setEditing] = useState<Equipment | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Equipment | null>(null);
    const [inspecting, setInspecting] = useState<Equipment | null>(null);
    const form = useForm(blank);
    const inspection = useForm(blankInspection);
    const site = sites.find((s) => String(s.id) === form.data.site_id);
    const today = localDay();
    const soon = localDay(dueSoonDays);

    const openForm = (e: Equipment | null) => {
        form.clearErrors();
        form.setData(
            e
                ? {
                      site_id: String(e.site_id),
                      area_id: String(e.area_id ?? ''),
                      asset_tag: e.asset_tag,
                      name: e.name,
                      type: e.type,
                      serial_number: e.serial_number ?? '',
                      owner_id: String(e.owner_id ?? ''),
                      inspection_interval_months: String(
                          e.inspection_interval_months,
                      ),
                      status: e.status,
                  }
                : {
                      ...blank,
                      site_id: String(sites.length === 1 ? sites[0].id : ''),
                  },
        );
        setEditing(e ?? 'new');
    };

    const openInspections = (e: Equipment) => {
        inspection.clearErrors();
        inspection.setData({ ...blankInspection, inspected_on: today });
        setInspecting(e);
    };

    const columns: Column<Equipment>[] = [
        {
            key: 'asset_tag',
            label: 'Equipment',
            sortable: true,
            className: 'min-w-56',
            render: (e) => (
                <div>
                    <div className="font-medium">
                        {e.asset_tag} · {e.name}
                    </div>
                    <div className="text-muted-foreground">
                        {[t(labelOf(EQUIPMENT_TYPES, e.type)), e.serial_number]
                            .filter(Boolean)
                            .join(' · ')}
                    </div>
                    <div className="text-muted-foreground">
                        {e.site.code}
                        {e.area && ` · ${e.area.name}`}
                        {e.owner && ` · ${e.owner.name}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'next_inspection_on',
            label: 'Next inspection',
            sortable: true,
            render: (e) => (
                <div className="grid justify-items-start gap-1">
                    {e.next_inspection_on ? (
                        <DateCell value={e.next_inspection_on} />
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    )}
                    {e.status === 'in-service' &&
                        (e.next_inspection_on === null ||
                        e.next_inspection_on < today ? (
                            <StatusBadge
                                status="overdue"
                                label={t(
                                    e.next_inspection_on
                                        ? 'Overdue'
                                        : 'Never inspected',
                                )}
                            />
                        ) : (
                            e.next_inspection_on <= soon && (
                                <StatusBadge
                                    status="pending"
                                    label={t('Due soon')}
                                />
                            )
                        ))}
                    <span className="text-xs text-muted-foreground">
                        {t('every :n months', {
                            n: e.inspection_interval_months,
                        })}
                    </span>
                </div>
            ),
        },
        {
            key: 'last_inspected_on',
            label: 'Last inspection',
            render: (e) => {
                const last = e.inspections[0];

                return last ? (
                    <div className="grid justify-items-start gap-1">
                        <DateCell value={last.inspected_on} />
                        <StatusBadge
                            status={last.passed ? 'verified' : 'overdue'}
                            label={t(last.passed ? 'Passed' : 'Failed')}
                        />
                        {last.file_name && (
                            <a
                                href={equipmentRoutes.certificate.url([
                                    e.id,
                                    last.id,
                                ])}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                            >
                                <FileText className="size-4" />
                                {t('Certificate')}
                            </a>
                        )}
                    </div>
                ) : (
                    <span className="text-muted-foreground">—</span>
                );
            },
        },
        {
            key: 'status',
            label: 'Status',
            render: (e) => <StatusBadge status={e.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Equipment register')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Equipment register"
                    description="Plant and equipment that needs a periodic inspection. Overdue or out-of-service equipment blocks approval of a permit that lists it."
                    action={
                        can('create-equipment') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add equipment')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={equipment}
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
                                name="due"
                                label="Any inspection date"
                                options={[
                                    { id: 'overdue', name: t('Overdue') },
                                    {
                                        id: 'soon',
                                        name: t('Due in :n days', {
                                            n: dueSoonDays,
                                        }),
                                    },
                                ]}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="type"
                                label="All types"
                                options={EQUIPMENT_TYPES.map((e) => ({
                                    id: e.value,
                                    name: t(e.label),
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
                    actions={(e) => (
                        <>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Inspections')}
                                onClick={() => openInspections(e)}
                            >
                                <ClipboardCheck />
                            </Button>
                            {can('edit-equipment') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(e)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-equipment') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(e)}
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
                title={editing === 'new' ? 'Add equipment' : 'Edit equipment'}
                description="Record each inspection from the clipboard button; the next due date follows from the interval."
                icon={Wrench}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(
                        editing === 'new' || editing === null
                            ? equipmentRoutes.store.url()
                            : equipmentRoutes.update.url(editing.id),
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
                    <div className="grid gap-2">
                        <Label htmlFor="eq-tag">
                            {t('Asset tag')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="eq-tag"
                            value={form.data.asset_tag}
                            onChange={(e) =>
                                form.setData('asset_tag', e.target.value)
                            }
                        />
                        <InputError message={form.errors.asset_tag} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="eq-type">
                            {t('Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="eq-type"
                            value={form.data.type}
                            onChange={(e) =>
                                form.setData('type', e.target.value)
                            }
                        >
                            <option value="">{t('Select type')}</option>
                            {EQUIPMENT_TYPES.map((e) => (
                                <option key={e.value} value={e.value}>
                                    {t(e.label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="eq-name">
                            {t('Description')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="eq-name"
                            placeholder={t('e.g. 50 t mobile crane')}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="eq-serial">{t('Serial number')}</Label>
                        <Input
                            id="eq-serial"
                            value={form.data.serial_number}
                            onChange={(e) =>
                                form.setData('serial_number', e.target.value)
                            }
                        />
                        <InputError message={form.errors.serial_number} />
                    </div>
                    {editing === 'new' && sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="eq-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="eq-site"
                                value={form.data.site_id}
                                onChange={(e) =>
                                    form.setData((d) => ({
                                        ...d,
                                        site_id: e.target.value,
                                        area_id: '',
                                        owner_id: '',
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
                        <Label htmlFor="eq-area">{t('Area')}</Label>
                        <SelectField
                            id="eq-area"
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
                        <InputError message={form.errors.area_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="eq-owner">
                            {t('Responsible person')}
                        </Label>
                        <SelectField
                            id="eq-owner"
                            value={form.data.owner_id}
                            onChange={(e) =>
                                form.setData('owner_id', e.target.value)
                            }
                        >
                            <option value="">{t('Nobody yet')}</option>
                            {people
                                .filter(
                                    (p) =>
                                        p.site_id === null ||
                                        String(p.site_id) === form.data.site_id,
                                )
                                .map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                        </SelectField>
                        <InputError message={form.errors.owner_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="eq-interval">
                            {t('Inspect every (months)')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="eq-interval"
                            inputMode="numeric"
                            value={form.data.inspection_interval_months}
                            onChange={(e) =>
                                form.setData(
                                    'inspection_interval_months',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={form.errors.inspection_interval_months}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label id="eq-status-label">{t('Status')}</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            aria-labelledby="eq-status-label"
                            value={form.data.status}
                            onValueChange={(v) =>
                                v && form.setData('status', v)
                            }
                            className="justify-start"
                        >
                            <ToggleGroupItem value="in-service">
                                {t('In service')}
                            </ToggleGroupItem>
                            <ToggleGroupItem value="out-of-service">
                                {t('Out of service')}
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <InputError message={form.errors.status} />
                    </div>
                </div>
            </FormDialog>

            <Dialog
                open={inspecting !== null}
                onOpenChange={(open) => !open && setInspecting(null)}
            >
                <DialogContent className="max-h-[90vh] gap-0 overflow-hidden p-0 sm:max-w-xl">
                    <DialogHeader className="flex-row items-center gap-3 border-b px-6 py-4 pe-12 text-left">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-400">
                            <ClipboardCheck className="size-5" />
                        </span>
                        <div className="grid gap-1">
                            <DialogTitle>
                                {t('Inspections of :tag', {
                                    tag: inspecting?.asset_tag ?? '',
                                })}
                            </DialogTitle>
                            <DialogDescription>
                                {t(
                                    'The latest inspection sets the due date. A pass puts the equipment in service; a failure takes it out.',
                                )}
                            </DialogDescription>
                        </div>
                    </DialogHeader>
                    {inspecting && (
                        <div className="grid gap-5 overflow-y-auto px-6 py-5">
                            {can('edit-equipment') && (
                                <form
                                    noValidate
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        inspection.post(
                                            equipmentRoutes.inspections.store.url(
                                                inspecting.id,
                                            ),
                                            {
                                                forceFormData: true,
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setInspecting(null),
                                            },
                                        );
                                    }}
                                    className="grid gap-4 rounded-md border p-4 sm:grid-cols-2"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="ins-date">
                                            {t('Inspected on')}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <DatePicker
                                            id="ins-date"
                                            value={inspection.data.inspected_on}
                                            onChange={(v) =>
                                                inspection.setData(
                                                    'inspected_on',
                                                    v,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                inspection.errors.inspected_on
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label id="ins-result-label">
                                            {t('Result')}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <ToggleGroup
                                            type="single"
                                            variant="outline"
                                            aria-labelledby="ins-result-label"
                                            value={inspection.data.passed}
                                            onValueChange={(v) =>
                                                v &&
                                                inspection.setData('passed', v)
                                            }
                                            className="justify-start"
                                        >
                                            <ToggleGroupItem value="1">
                                                {t('Passed')}
                                            </ToggleGroupItem>
                                            <ToggleGroupItem value="0">
                                                {t('Failed')}
                                            </ToggleGroupItem>
                                        </ToggleGroup>
                                        <InputError
                                            message={inspection.errors.passed}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="ins-inspector">
                                            {t('Inspected by')}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="ins-inspector"
                                            placeholder={t(
                                                'Competent person or inspection body',
                                            )}
                                            value={inspection.data.inspector}
                                            onChange={(e) =>
                                                inspection.setData(
                                                    'inspector',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                inspection.errors.inspector
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="ins-notes">
                                            {t('Defects / notes')}
                                        </Label>
                                        <Textarea
                                            id="ins-notes"
                                            rows={2}
                                            value={inspection.data.notes}
                                            onChange={(e) =>
                                                inspection.setData(
                                                    'notes',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={inspection.errors.notes}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="ins-cert">
                                            {t('Certificate')}
                                        </Label>
                                        <Input
                                            id="ins-cert"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            onChange={(e) =>
                                                inspection.setData(
                                                    'certificate',
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                inspection.errors.certificate
                                            }
                                        />
                                    </div>
                                    <div className="flex justify-end sm:col-span-2">
                                        <Button
                                            type="submit"
                                            disabled={inspection.processing}
                                        >
                                            {inspection.processing && (
                                                <LoaderCircle className="animate-spin" />
                                            )}
                                            {t('Record inspection')}
                                        </Button>
                                    </div>
                                </form>
                            )}
                            <ul className="divide-y rounded-md border text-sm">
                                {inspecting.inspections.length === 0 && (
                                    <li className="p-3 text-muted-foreground">
                                        {t('No inspections recorded yet.')}
                                    </li>
                                )}
                                {inspecting.inspections.map((i) => (
                                    <li key={i.id} className="grid gap-1 p-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <DateCell value={i.inspected_on} />
                                            <StatusBadge
                                                status={
                                                    i.passed
                                                        ? 'verified'
                                                        : 'overdue'
                                                }
                                                label={t(
                                                    i.passed
                                                        ? 'Passed'
                                                        : 'Failed',
                                                )}
                                            />
                                            <span className="text-muted-foreground">
                                                {i.inspector}
                                            </span>
                                            {i.file_name && (
                                                <a
                                                    href={equipmentRoutes.certificate.url(
                                                        [inspecting.id, i.id],
                                                    )}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                                                >
                                                    <FileText className="size-4" />
                                                    {t('Certificate')}
                                                </a>
                                            )}
                                        </div>
                                        {i.notes && <p>{i.notes}</p>}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Remove equipment"
                description="It is removed from the register with its inspection history. Equipment named on a permit can only be marked out of service."
                confirmLabel="Remove"
                onConfirm={() =>
                    deleting &&
                    router.delete(equipmentRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

EquipmentRegister.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Equipment register', href: equipmentRoutes.index() },
    ],
};
