import { Head, router, useForm } from '@inertiajs/react';
import { Award, FileText, GraduationCap, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { PERMIT_TYPES, labelOf } from '@/lib/hse';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import competencyRoutes from '@/routes/competencies';

type Competency = {
    id: number;
    name: string;
    validity_months: number | null;
    permit_type: string | null;
};

type Record_ = {
    id: number;
    competency_id: number;
    issued_on: string;
    expires_on: string | null;
    reference: string | null;
    has_file: boolean;
};

type Person = { id: number; name: string; records: Record_[] };

const today = () => new Date().toLocaleDateString('en-CA');

/** valid / expiring (within the warning window) / expired, by calendar day. */
function state(record: Record_, expiringDays: number) {
    if (!record.expires_on) {
        return 'valid';
    }

    const days =
        (new Date(`${record.expires_on}T23:59:59`).getTime() - Date.now()) /
        86_400_000;

    return days < 0 ? 'expired' : days <= expiringDays ? 'expiring' : 'valid';
}

const CELL: Record<string, string> = {
    valid: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    expiring:
        'bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
    expired: 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200',
};

export default function Competencies({
    competencies,
    people,
    expiringDays,
    filters,
}: {
    competencies: Competency[];
    people: Person[];
    permitTypes: string[];
    expiringDays: number;
    filters: { search?: string };
}) {
    const { t } = useTranslation();
    const { date } = useFormat();
    const can = useCan();
    const editable = can('edit-competencies');
    const [search, setSearch] = useState(filters.search ?? '');
    const [recording, setRecording] = useState(false);
    const [competencyFor, setCompetencyFor] = useState<
        Competency | 'new' | null
    >(null);
    const record = useForm({
        user_id: '',
        competency_id: '',
        issued_on: today(),
        expires_on: '',
        reference: '',
        file: null as File | null,
    });
    const competency = useForm({
        name: '',
        validity_months: '',
        permit_type: '',
    });

    const openRecord = (userId?: number, competencyId?: number) => {
        record.clearErrors();
        record.setData({
            user_id: String(userId ?? ''),
            competency_id: String(competencyId ?? ''),
            issued_on: today(),
            expires_on: '',
            reference: '',
            file: null,
        });
        setRecording(true);
    };

    const openCompetency = (c: Competency | null) => {
        competency.clearErrors();
        competency.setData({
            name: c?.name ?? '',
            validity_months: String(c?.validity_months ?? ''),
            permit_type: c?.permit_type ?? '',
        });
        setCompetencyFor(c ?? 'new');
    };

    return (
        <>
            <Head title={t('Training matrix')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Training matrix"
                    description="Who holds which competency, and until when. Permits check the competencies tied to their type."
                    action={
                        editable && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => openCompetency(null)}
                                >
                                    <Award /> {t('New competency')}
                                </Button>
                                <Button onClick={() => openRecord()}>
                                    <Plus /> {t('Record training')}
                                </Button>
                            </div>
                        )
                    }
                />

                <form
                    noValidate
                    className="flex max-w-sm gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            competencyRoutes.index.url({
                                query: { search: search || undefined },
                            }),
                            {},
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        aria-label={t('Search people')}
                        placeholder={t('Search people…')}
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button type="submit" variant="outline" size="icon">
                        <Search />
                    </Button>
                </form>

                <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
                    {(['valid', 'expiring', 'expired'] as const).map((s) => (
                        <span key={s} className="flex items-center gap-1.5">
                            <span
                                className={cn('size-3 rounded-sm', CELL[s])}
                            />
                            {s === 'expiring'
                                ? t('Expires within :n days', {
                                      n: expiringDays,
                                  })
                                : t(s.charAt(0).toUpperCase() + s.slice(1))}
                        </span>
                    ))}
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="sticky start-0 bg-muted/50 px-3 py-2 font-medium">
                                    {t('Person')}
                                </th>
                                {competencies.map((c) => (
                                    <th
                                        key={c.id}
                                        className="min-w-36 px-3 py-2 font-medium"
                                    >
                                        <button
                                            type="button"
                                            disabled={!editable}
                                            onClick={() => openCompetency(c)}
                                            className="text-left enabled:hover:underline"
                                        >
                                            {c.name}
                                        </button>
                                        <div className="text-xs font-normal text-muted-foreground">
                                            {c.validity_months
                                                ? t(':n months', {
                                                      n: c.validity_months,
                                                  })
                                                : t('No expiry')}
                                            {c.permit_type &&
                                                ` · ${t(labelOf(PERMIT_TYPES, c.permit_type))}`}
                                        </div>
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {people.map((p) => (
                                <tr key={p.id}>
                                    <td className="sticky start-0 bg-background px-3 py-2 font-medium whitespace-nowrap">
                                        {p.name}
                                    </td>
                                    {competencies.map((c) => {
                                        const r = p.records.find(
                                            (x) => x.competency_id === c.id,
                                        );
                                        const s = r
                                            ? state(r, expiringDays)
                                            : null;

                                        return (
                                            <td key={c.id} className="p-1">
                                                <button
                                                    type="button"
                                                    disabled={!editable}
                                                    aria-label={t(
                                                        ':person, :competency',
                                                        {
                                                            person: p.name,
                                                            competency: c.name,
                                                        },
                                                    )}
                                                    onClick={() =>
                                                        openRecord(p.id, c.id)
                                                    }
                                                    className={cn(
                                                        'flex h-10 w-full items-center justify-center gap-1 rounded-md px-2 text-xs',
                                                        s
                                                            ? CELL[s]
                                                            : 'text-muted-foreground enabled:hover:bg-muted',
                                                    )}
                                                >
                                                    {r
                                                        ? r.expires_on
                                                            ? date(r.expires_on)
                                                            : t('Held')
                                                        : '—'}
                                                    {r?.has_file && (
                                                        <FileText className="size-3" />
                                                    )}
                                                </button>
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <FormDialog
                open={recording}
                onOpenChange={setRecording}
                title="Record training"
                description="The expiry follows from the competency's validity unless you set it."
                icon={GraduationCap}
                onSubmit={(e) => {
                    e.preventDefault();
                    record.post(competencyRoutes.records.store.url(), {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => setRecording(false),
                    });
                }}
                processing={record.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="rec-person">
                            {t('Person')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="rec-person"
                            value={record.data.user_id}
                            onChange={(e) =>
                                record.setData('user_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select person')}</option>
                            {people.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={record.errors.user_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rec-competency">
                            {t('Competency')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="rec-competency"
                            value={record.data.competency_id}
                            onChange={(e) =>
                                record.setData('competency_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select competency')}</option>
                            {competencies.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={record.errors.competency_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rec-issued">
                            {t('Issued on')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="rec-issued"
                            value={record.data.issued_on}
                            onChange={(v) => record.setData('issued_on', v)}
                        />
                        <InputError message={record.errors.issued_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rec-expires">{t('Expires on')}</Label>
                        <DatePicker
                            id="rec-expires"
                            placeholder="From the validity"
                            value={record.data.expires_on}
                            onChange={(v) => record.setData('expires_on', v)}
                        />
                        <InputError message={record.errors.expires_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rec-reference">
                            {t('Certificate no.')}
                        </Label>
                        <Input
                            id="rec-reference"
                            value={record.data.reference}
                            onChange={(e) =>
                                record.setData('reference', e.target.value)
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rec-file">{t('Certificate')}</Label>
                        <Input
                            id="rec-file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) =>
                                record.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={record.errors.file} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={competencyFor !== null}
                onOpenChange={(open) => !open && setCompetencyFor(null)}
                title={
                    competencyFor === 'new'
                        ? 'New competency'
                        : 'Edit competency'
                }
                description="Tie it to a permit type to require it of every worker on permits of that type."
                icon={Award}
                onSubmit={(e) => {
                    e.preventDefault();
                    competency.submit(
                        competencyFor === 'new' || competencyFor === null
                            ? competencyRoutes.store()
                            : competencyRoutes.update(competencyFor.id),
                        {
                            preserveScroll: true,
                            onSuccess: () => setCompetencyFor(null),
                        },
                    );
                }}
                processing={competency.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="comp-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="comp-name"
                            placeholder={t('e.g. Confined space entry')}
                            value={competency.data.name}
                            onChange={(e) =>
                                competency.setData('name', e.target.value)
                            }
                        />
                        <InputError message={competency.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="comp-validity">
                            {t('Valid for (months)')}
                        </Label>
                        <Input
                            id="comp-validity"
                            inputMode="numeric"
                            placeholder={t('Blank: no expiry')}
                            value={competency.data.validity_months}
                            onChange={(e) =>
                                competency.setData(
                                    'validity_months',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError
                            message={competency.errors.validity_months}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="comp-permit">
                            {t('Required for permits of type')}
                        </Label>
                        <SelectField
                            id="comp-permit"
                            value={competency.data.permit_type}
                            onChange={(e) =>
                                competency.setData(
                                    'permit_type',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('None')}</option>
                            {PERMIT_TYPES.map((p) => (
                                <option key={p.value} value={p.value}>
                                    {t(p.label)}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

Competencies.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Training matrix', href: competencyRoutes.index() },
    ],
};
