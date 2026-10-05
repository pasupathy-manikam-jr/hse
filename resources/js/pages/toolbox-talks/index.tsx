import { Head, Link, router, useForm } from '@inertiajs/react';
import { Megaphone, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PeoplePicker } from '@/components/people-picker';
import { SelectField } from '@/components/select-field';
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import incidentRoutes from '@/routes/incidents';
import talkRoutes from '@/routes/toolbox-talks';
import type { Paginated, TableFilters } from '@/types';

type Talk = {
    id: number;
    number: string;
    topic: string;
    notes: string | null;
    held_on: string;
    site: { id: number; code: string };
    presenter: { id: number; name: string } | null;
    incident: { id: number; number: string } | null;
    attendees: { id: number; name: string }[];
    attendees_count: number;
};

const today = () => new Date().toLocaleDateString('en-CA');

export default function ToolboxTalks({
    talks,
    sites,
    people,
    incidents,
    filters,
}: {
    talks: Paginated<Talk>;
    sites: { id: number; code: string; name: string }[];
    people: { id: number; name: string; site_id: number | null }[];
    incidents: { id: number; number: string; title: string; site_id: number }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<Talk | null>(null);
    const form = useForm({
        site_id: String(sites.length === 1 ? sites[0].id : ''),
        topic: '',
        notes: '',
        held_on: today(),
        presenter_id: '',
        incident_id: '',
        attendee_ids: [] as number[],
    });
    const atSite = (siteId: number | null) =>
        siteId === null || String(siteId) === form.data.site_id;

    const columns: Column<Talk>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (talk) => <IdBadge>{talk.number}</IdBadge>,
        },
        {
            key: 'topic',
            label: 'Topic',
            className: 'min-w-64',
            render: (talk) => (
                <div>
                    <div className="font-medium">{talk.topic}</div>
                    <div className="text-muted-foreground">
                        {talk.site.code}
                        {talk.incident && (
                            <>
                                {' · '}
                                {t('lessons from')}{' '}
                                <Link
                                    href={incidentRoutes.show(talk.incident.id)}
                                    className="text-blue-600 hover:underline"
                                >
                                    {talk.incident.number}
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            ),
        },
        {
            key: 'held_on',
            label: 'Held',
            sortable: true,
            render: (talk) => (
                <div className="grid gap-0.5">
                    <DateCell value={talk.held_on} />
                    <span className="text-muted-foreground">
                        {talk.presenter?.name}
                    </span>
                </div>
            ),
        },
        {
            key: 'attendees',
            label: 'Attendees',
            render: (talk) => (
                <span
                    title={talk.attendees.map((a) => a.name).join(', ')}
                    className="cursor-help underline decoration-dotted"
                >
                    {talk.attendees_count}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Toolbox talks')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Toolbox talks"
                    description="Short safety briefings and who attended, including lessons from incidents."
                    action={
                        can('create-toolbox-talks') && (
                            <Button
                                onClick={() => {
                                    form.clearErrors();
                                    setCreating(true);
                                }}
                            >
                                <Plus /> {t('Record talk')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={talks}
                    columns={columns}
                    filters={filters}
                    url={talkRoutes.index()}
                    toolbar={
                        sites.length > 1 && (
                            <FilterSelect
                                url={talkRoutes.index()}
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
                    actions={(talk) =>
                        can('delete-toolbox-talks') && (
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t('Delete')}
                                onClick={() => setDeleting(talk)}
                            >
                                <Trash2 />
                            </Button>
                        )
                    }
                />
            </div>

            <FormDialog
                open={creating}
                onOpenChange={setCreating}
                title="Record toolbox talk"
                description="The topic, who gave it, and everyone who attended."
                icon={Megaphone}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(talkRoutes.store.url(), {
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
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="tbt-topic">
                            {t('Topic')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="tbt-topic"
                            placeholder={t(
                                'e.g. Working near the crane swing radius',
                            )}
                            value={form.data.topic}
                            onChange={(e) =>
                                form.setData('topic', e.target.value)
                            }
                        />
                        <InputError message={form.errors.topic} />
                    </div>
                    {sites.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="tbt-site">
                                {t('Site')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <SelectField
                                id="tbt-site"
                                value={form.data.site_id}
                                onChange={(e) =>
                                    form.setData((d) => ({
                                        ...d,
                                        site_id: e.target.value,
                                        incident_id: '',
                                        attendee_ids: [],
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
                        <Label htmlFor="tbt-date">
                            {t('Held on')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="tbt-date"
                            value={form.data.held_on}
                            onChange={(v) => form.setData('held_on', v)}
                        />
                        <InputError message={form.errors.held_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tbt-presenter">
                            {t('Given by')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="tbt-presenter"
                            value={form.data.presenter_id}
                            onChange={(e) =>
                                form.setData('presenter_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select person')}</option>
                            {people
                                .filter((p) => atSite(p.site_id))
                                .map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                        </SelectField>
                        <InputError message={form.errors.presenter_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tbt-incident">
                            {t('Lessons from an incident')}
                        </Label>
                        <SelectField
                            id="tbt-incident"
                            value={form.data.incident_id}
                            onChange={(e) =>
                                form.setData('incident_id', e.target.value)
                            }
                        >
                            <option value="">{t('None')}</option>
                            {incidents
                                .filter((i) => atSite(i.site_id))
                                .map((i) => (
                                    <option key={i.id} value={i.id}>
                                        {i.number} · {i.title}
                                    </option>
                                ))}
                        </SelectField>
                        <InputError message={form.errors.incident_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="tbt-notes">{t('Key points')}</Label>
                        <Textarea
                            id="tbt-notes"
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="tbt-attendees">
                            {t('Attendees')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <PeoplePicker
                            id="tbt-attendees"
                            people={people.filter((p) => atSite(p.site_id))}
                            value={form.data.attendee_ids}
                            onChange={(ids) =>
                                form.setData('attendee_ids', ids)
                            }
                        />
                        <InputError message={form.errors.attendee_ids} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Delete toolbox talk"
                description="This talk and its attendance list will be deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(talkRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

ToolboxTalks.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Toolbox talks', href: talkRoutes.index() },
    ],
};
