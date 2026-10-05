import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, Lock, Plus, Repeat } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { PERMIT_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import handoverRoutes from '@/routes/handovers';
import type { Paginated, TableFilters } from '@/types';

type OpenPermit = {
    id: number;
    number: string;
    type: string;
    status: string;
    area: string | null;
    valid_to: string;
    isolations: { point: string; lock_no: string | null }[];
};

type Handover = {
    id: number;
    number: string;
    shift: 'day' | 'night';
    notes: string;
    open_permits: OpenPermit[];
    acknowledged_at: string | null;
    to_user_id: number;
    created_at: string;
    site: { id: number; code: string };
    creator: { id: number; name: string } | null;
    recipient: { id: number; name: string };
};

export default function Handovers({
    handovers,
    sites,
    people,
    filters,
}: {
    handovers: Paginated<Handover>;
    sites: { id: number; code: string; name: string }[];
    people: { id: number; name: string; site_id: number | null }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const { auth } = usePage().props as { auth: { user: { id: number } } };
    const [creating, setCreating] = useState(false);
    const form = useForm({
        site_id: String(sites.length === 1 ? sites[0].id : ''),
        shift: 'day',
        to_user_id: '',
        notes: '',
    });

    const columns: Column<Handover>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (h) => <IdBadge>{h.number}</IdBadge>,
        },
        {
            key: 'handover',
            label: 'Handover',
            className: 'min-w-72',
            render: (h) => (
                <div className="grid gap-1">
                    <div className="font-medium">
                        {h.site.code} ·{' '}
                        {t(h.shift === 'day' ? 'Day shift' : 'Night shift')}
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            · {h.creator?.name ?? '—'} → {h.recipient.name}
                        </span>
                    </div>
                    <p className="text-sm whitespace-pre-line">{h.notes}</p>
                    {h.open_permits.length > 0 && (
                        <ul className="grid gap-0.5 text-xs text-muted-foreground">
                            {h.open_permits.map((p) => (
                                <li key={p.id}>
                                    {p.number} ·{' '}
                                    {t(labelOf(PERMIT_TYPES, p.type))} (
                                    {p.status}){p.area && ` · ${p.area}`} ·{' '}
                                    {t('until :t', { t: dateTime(p.valid_to) })}
                                    {p.isolations.length > 0 && (
                                        <span className="ms-1 inline-flex items-center gap-1 text-red-700 dark:text-red-400">
                                            <Lock className="size-3" />
                                            {p.isolations
                                                .map(
                                                    (i) => i.lock_no ?? i.point,
                                                )
                                                .join(', ')}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ),
        },
        {
            key: 'created_at',
            label: 'Handed over',
            sortable: true,
            render: (h) => (
                <span className="whitespace-nowrap">
                    {dateTime(h.created_at)}
                </span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (h) =>
                h.acknowledged_at ? (
                    <StatusBadge status="verified" label={t('Acknowledged')} />
                ) : (
                    <StatusBadge status="pending" />
                ),
        },
    ];

    return (
        <>
            <Head title={t('Shift handovers')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Shift handovers"
                    description="What the outgoing supervisor passes on. Open permits and the locks still on are captured automatically."
                    action={
                        can('create-permits') && (
                            <Button
                                onClick={() => {
                                    form.clearErrors();
                                    setCreating(true);
                                }}
                            >
                                <Plus /> {t('Hand over')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={handovers}
                    columns={columns}
                    filters={filters}
                    url={handoverRoutes.index()}
                    actions={(h) =>
                        !h.acknowledged_at &&
                        h.to_user_id === auth.user.id && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    router.put(
                                        handoverRoutes.acknowledge.url(h.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Check /> {t('Acknowledge')}
                            </Button>
                        )
                    }
                />
            </div>

            <FormDialog
                open={creating}
                onOpenChange={setCreating}
                title="Hand over the shift"
                description="The open permits and isolations at the site are attached when you save."
                icon={Repeat}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(handoverRoutes.store.url(), {
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset();
                            setCreating(false);
                        },
                    });
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="sho-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="sho-site"
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
                        <Label id="sho-shift-label">{t('Shift ending')}</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            aria-labelledby="sho-shift-label"
                            value={form.data.shift}
                            onValueChange={(v) => v && form.setData('shift', v)}
                            className="w-full"
                        >
                            <ToggleGroupItem value="day" className="flex-1">
                                {t('Day')}
                            </ToggleGroupItem>
                            <ToggleGroupItem value="night" className="flex-1">
                                {t('Night')}
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="sho-to">
                            {t('Handing over to')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="sho-to"
                            value={form.data.to_user_id}
                            onChange={(e) =>
                                form.setData('to_user_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select person')}</option>
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
                        <InputError message={form.errors.to_user_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="sho-notes">
                            {t('Hazards, ongoing work and anything unusual')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="sho-notes"
                            rows={4}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

Handovers.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Shift handovers', href: handoverRoutes.index() },
    ],
};
