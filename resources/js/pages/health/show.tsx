import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleAlert,
    FileText,
    Gauge,
    HeartPulse,
    SquarePen,
    Stethoscope,
    UserMinus,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import { HealthProgrammeFields } from '@/components/health-programme-fields';
import type { ProgrammeFormData } from '@/components/health-programme-fields';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import {
    EXAM_TYPES,
    HEALTH_HAZARDS,
    HEALTH_OUTCOMES,
    labelOf,
} from '@/lib/hse';
import { dashboard } from '@/routes';
import healthRoutes from '@/routes/health';

type Person = { id: number; name: string };

type Exam = {
    id: number;
    user_id: number;
    type: string;
    examined_on: string;
    provider: string;
    outcome: string;
    notes: string | null;
    informed_on: string | null;
    dosh_notified_on: string | null;
    dosh_reference: string | null;
    file_name: string | null;
    notify_dosh: boolean;
    user: Person;
};

type Row = {
    member_id: number;
    user_id: number;
    name: string;
    started_on: string;
    latest: Exam | null;
    next_type: string | null;
    due_on: string | null;
    state: 'overdue' | 'due-soon' | 'ok' | 'removed';
};

type Reading = {
    id: number;
    measured_on: string;
    value: string;
    assessor: string;
    notes: string | null;
    file_name: string | null;
    user: Person | null;
};

type Programme = {
    id: number;
    site_id: number;
    name: string;
    hazard: 'noise' | 'chemical';
    chemical_id: number | null;
    schedule_ii: boolean;
    exam_interval_months: number;
    monitoring_interval_months: number | null;
    exposure_limit: string | null;
    unit: string | null;
    active: boolean;
    site: { id: number; code: string; name: string };
    chemical: { id: number; name: string } | null;
};

/** Local Y-m-d, for date defaults. */
const today = () => {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

export default function ShowHealthProgramme({
    programme: p,
    schedule,
    exams,
    readings,
    monitoringDueOn,
    outcomes,
    chemicals,
    people,
}: {
    programme: Programme;
    schedule: Row[];
    exams: Exam[];
    readings: Reading[];
    monitoringDueOn: string | null;
    outcomes: string[];
    chemicals: { id: number; site_id: number; name: string }[];
    people: Person[];
}) {
    const { t } = useTranslation();
    const can = useCan();
    const editable = can('edit-health');
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [dialog, setDialog] = useState<
        'edit' | 'member' | 'exam' | 'reading' | null
    >(null);
    const [ending, setEnding] = useState<Row | null>(null);
    const [informing, setInforming] = useState<Exam | null>(null);
    const [notifying, setNotifying] = useState<Exam | null>(null);

    const details = useForm<ProgrammeFormData>({
        site_id: String(p.site_id),
        name: p.name,
        hazard: p.hazard,
        chemical_id: String(p.chemical_id ?? ''),
        schedule_ii: p.schedule_ii,
        exam_interval_months: String(p.exam_interval_months),
        monitoring_interval_months: String(p.monitoring_interval_months ?? ''),
        exposure_limit:
            p.exposure_limit === null ? '' : String(Number(p.exposure_limit)),
        unit: p.unit ?? '',
        active: p.active,
    });
    const member = useForm({ user_id: '', started_on: today() });
    const end = useForm({ ended_on: today() });
    const exam = useForm({
        user_id: '',
        type: 'periodic',
        examined_on: today(),
        provider: '',
        outcome: '',
        notes: '',
        file: null as File | null,
    });
    const informed = useForm({ informed_on: today() });
    const notified = useForm({ dosh_notified_on: today(), dosh_reference: '' });
    const reading = useForm({
        measured_on: today(),
        value: '',
        user_id: '',
        assessor: '',
        notes: '',
        file: null as File | null,
    });

    const open = (which: 'edit' | 'member' | 'exam' | 'reading') => {
        details.clearErrors();
        member.clearErrors();
        exam.clearErrors();
        reading.clearErrors();
        setDialog(which);
    };
    const recordFor = (row: Row) => {
        exam.setData((d) => ({
            ...d,
            user_id: String(row.user_id),
            type: row.next_type ?? 'periodic',
            examined_on: today(),
            outcome: '',
            notes: '',
            file: null,
        }));
        open('exam');
    };
    const close = { preserveScroll: true, onSuccess: () => setDialog(null) };
    const overLimit = (value: string) =>
        p.exposure_limit !== null && Number(value) > Number(p.exposure_limit);
    const members = schedule.map((row) => ({
        id: row.user_id,
        name: row.name,
    }));
    const inProgramme = new Set(schedule.map((row) => row.user_id));

    const facts: [string, ReactNode][] = [
        ['Exposure', t(labelOf(HEALTH_HAZARDS, p.hazard))],
        ...(p.chemical
            ? ([['Chemical', p.chemical.name]] as [string, ReactNode][])
            : []),
        ...(p.schedule_ii
            ? ([['USECHH', t('Schedule II')]] as [string, ReactNode][])
            : []),
        ['Examinations', t('every :n months', { n: p.exam_interval_months })],
        [
            'Monitoring',
            p.monitoring_interval_months
                ? t('every :n months', { n: p.monitoring_interval_months })
                : t('not required'),
        ],
        [
            'Exposure limit',
            p.exposure_limit === null
                ? '—'
                : `${Number(p.exposure_limit)} ${p.unit ?? ''}`,
        ],
    ];

    return (
        <>
            <Head title={p.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={p.name}
                    description={`${p.site.code} · ${p.site.name}${p.active ? '' : ` · ${t('inactive')}`}`}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={healthRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {editable && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() => open('edit')}
                                    >
                                        <SquarePen /> {t('Edit')}
                                    </Button>
                                    <Button onClick={() => open('member')}>
                                        <UserPlus /> {t('Add worker')}
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

                {errors.status && (
                    <div
                        role="alert"
                        className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    >
                        <CircleAlert className="size-4 shrink-0" />
                        {errors.status}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="grid content-start gap-3 rounded-xl border p-5">
                        <h2 className="font-medium">{t('Programme')}</h2>
                        <dl className="grid gap-2 text-sm">
                            {facts.map(([label, value]) => (
                                <div
                                    key={label}
                                    className="grid grid-cols-[8rem_1fr] gap-2"
                                >
                                    <dt className="text-muted-foreground">
                                        {t(label)}
                                    </dt>
                                    <dd className="font-medium">{value}</dd>
                                </div>
                            ))}
                        </dl>
                        <p className="text-xs text-muted-foreground">
                            {p.hazard === 'noise'
                                ? t(
                                      'Baseline audiogram within 3 months of starting noisy work, then yearly at an approved audiometric test centre. Tell the worker within 21 days; a permanent shift, hearing impairment or NIHL goes to DOSH within 7 days.',
                                  )
                                : t(
                                      'Medical surveillance by an occupational health doctor. A worker found unfit is removed from the exposure (USECHH reg. 28). Records are kept 30 years.',
                                  )}
                        </p>
                    </section>

                    <section className="grid content-start gap-3 rounded-xl border p-5 lg:col-span-2">
                        <h2 className="font-medium">{t('Exposed workers')}</h2>
                        {schedule.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No workers yet. Add each person exposed.')}
                            </p>
                        ) : (
                            <ul className="divide-y rounded-md border text-sm">
                                {schedule.map((row) => (
                                    <li
                                        key={row.member_id}
                                        className="flex flex-wrap items-center justify-between gap-3 px-3 py-2"
                                    >
                                        <div className="grid gap-0.5">
                                            <span className="font-medium">
                                                {row.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {t('Exposed since :d', {
                                                    d: row.started_on,
                                                })}
                                                {row.latest &&
                                                    ` · ${t('last :type :d', {
                                                        type: t(
                                                            labelOf(
                                                                EXAM_TYPES,
                                                                row.latest.type,
                                                            ),
                                                        ).toLowerCase(),
                                                        d: row.latest
                                                            .examined_on,
                                                    })}`}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            {row.latest && (
                                                <StatusBadge
                                                    status={row.latest.outcome}
                                                    label={t(
                                                        labelOf(
                                                            HEALTH_OUTCOMES,
                                                            row.latest.outcome,
                                                        ),
                                                    )}
                                                />
                                            )}
                                            {row.state === 'removed' ? (
                                                <StatusBadge
                                                    status="removed"
                                                    label={t(
                                                        'Removed from exposure',
                                                    )}
                                                />
                                            ) : (
                                                row.due_on && (
                                                    <StatusBadge
                                                        status={
                                                            row.state === 'ok'
                                                                ? 'pending'
                                                                : row.state
                                                        }
                                                        label={t(
                                                            ':type due :d',
                                                            {
                                                                type: t(
                                                                    labelOf(
                                                                        EXAM_TYPES,
                                                                        row.next_type ??
                                                                            '',
                                                                    ),
                                                                ),
                                                                d: row.due_on,
                                                            },
                                                        )}
                                                    />
                                                )
                                            )}
                                            {editable && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            recordFor(row)
                                                        }
                                                    >
                                                        <Stethoscope />{' '}
                                                        {t('Record result')}
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={t(
                                                            'End exposure for :name',
                                                            { name: row.name },
                                                        )}
                                                        onClick={() => {
                                                            end.clearErrors();
                                                            setEnding(row);
                                                        }}
                                                    >
                                                        <UserMinus />
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <section className="grid gap-3 rounded-xl border p-5">
                    <h2 className="font-medium">{t('Results')}</h2>
                    {exams.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No results recorded yet.')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr className="[&>th]:py-1.5 [&>th]:pe-3 [&>th]:font-medium">
                                        <th>{t('Date')}</th>
                                        <th>{t('Worker')}</th>
                                        <th>{t('Type')}</th>
                                        <th>{t('Result')}</th>
                                        <th>{t('By')}</th>
                                        <th>{t('Worker told')}</th>
                                        <th>{t('DOSH')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {exams.map((e) => (
                                        <tr
                                            key={e.id}
                                            className="align-top [&>td]:py-1.5 [&>td]:pe-3"
                                        >
                                            <td className="whitespace-nowrap">
                                                <DateCell
                                                    value={e.examined_on}
                                                />
                                            </td>
                                            <td>{e.user.name}</td>
                                            <td>
                                                {t(labelOf(EXAM_TYPES, e.type))}
                                            </td>
                                            <td>
                                                <StatusBadge
                                                    status={e.outcome}
                                                    label={t(
                                                        labelOf(
                                                            HEALTH_OUTCOMES,
                                                            e.outcome,
                                                        ),
                                                    )}
                                                />
                                                {e.notes && (
                                                    <p className="mt-1 text-muted-foreground">
                                                        {e.notes}
                                                    </p>
                                                )}
                                            </td>
                                            <td>
                                                {e.provider}
                                                {e.file_name && (
                                                    <a
                                                        href={healthRoutes.exams.file.url(
                                                            e.id,
                                                        )}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="ms-2 inline-flex items-center gap-1 text-blue-600 hover:underline"
                                                    >
                                                        <FileText className="size-4" />
                                                        {t('Report')}
                                                    </a>
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                {e.informed_on ? (
                                                    <DateCell
                                                        value={e.informed_on}
                                                    />
                                                ) : editable ? (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => {
                                                            informed.clearErrors();
                                                            setInforming(e);
                                                        }}
                                                    >
                                                        {t('Record')}
                                                    </Button>
                                                ) : (
                                                    <StatusBadge
                                                        status="pending"
                                                        label={t('Not yet')}
                                                    />
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap">
                                                {!e.notify_dosh ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : e.dosh_notified_on ? (
                                                    <span>
                                                        <DateCell
                                                            value={
                                                                e.dosh_notified_on
                                                            }
                                                        />{' '}
                                                        <span className="text-muted-foreground">
                                                            {e.dosh_reference}
                                                        </span>
                                                    </span>
                                                ) : editable ? (
                                                    <Button
                                                        size="sm"
                                                        onClick={() => {
                                                            notified.clearErrors();
                                                            setNotifying(e);
                                                        }}
                                                    >
                                                        {t('Notify DOSH')}
                                                    </Button>
                                                ) : (
                                                    <StatusBadge
                                                        status="overdue"
                                                        label={t('To notify')}
                                                    />
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="grid gap-3 rounded-xl border p-5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="font-medium">
                                {t('Exposure monitoring')}
                            </h2>
                            {monitoringDueOn && (
                                <p className="text-sm text-muted-foreground">
                                    {t('Next due :d', { d: monitoringDueOn })}
                                </p>
                            )}
                        </div>
                        {editable && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => open('reading')}
                            >
                                <Gauge /> {t('Add reading')}
                            </Button>
                        )}
                    </div>
                    {readings.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No readings yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border text-sm">
                            {readings.map((r) => (
                                <li
                                    key={r.id}
                                    className="flex flex-wrap items-center justify-between gap-2 px-3 py-2"
                                >
                                    <span className="flex flex-wrap items-center gap-2">
                                        <DateCell value={r.measured_on} />
                                        <span
                                            className={
                                                overLimit(r.value)
                                                    ? 'font-semibold text-red-700 dark:text-red-400'
                                                    : 'font-medium'
                                            }
                                        >
                                            {Number(r.value)} {p.unit}
                                        </span>
                                        {overLimit(r.value) && (
                                            <StatusBadge
                                                status="overdue"
                                                label={t('Above the limit')}
                                            />
                                        )}
                                        <span className="text-muted-foreground">
                                            {r.user
                                                ? t('personal: :name', {
                                                      name: r.user.name,
                                                  })
                                                : t('area')}
                                        </span>
                                    </span>
                                    <span className="text-muted-foreground">
                                        {r.assessor}
                                        {r.file_name && (
                                            <a
                                                href={healthRoutes.readings.file.url(
                                                    r.id,
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="ms-2 inline-flex items-center gap-1 text-blue-600 hover:underline"
                                            >
                                                <FileText className="size-4" />
                                                {t('Report')}
                                            </a>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <FormDialog
                open={dialog === 'edit'}
                onOpenChange={(o) => !o && setDialog(null)}
                title="Edit programme"
                description="Intervals, limit and whether it is still active."
                icon={HeartPulse}
                onSubmit={(e) => {
                    e.preventDefault();
                    details.transform(({ hazard: _hazard, ...rest }) => rest);
                    details.put(healthRoutes.update.url(p.id), close);
                }}
                processing={details.processing}
            >
                <HealthProgrammeFields
                    form={details}
                    sites={[]}
                    chemicals={chemicals}
                    editing
                />
            </FormDialog>

            <FormDialog
                open={dialog === 'member'}
                onOpenChange={(o) => !o && setDialog(null)}
                title="Add worker"
                description="Someone exposed under this programme, from the day the exposure began."
                icon={UserPlus}
                onSubmit={(e) => {
                    e.preventDefault();
                    member.post(healthRoutes.members.store.url(p.id), close);
                }}
                processing={member.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="hm-user">
                            {t('Worker')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="hm-user"
                            value={member.data.user_id}
                            onChange={(e) =>
                                member.setData('user_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select person')}</option>
                            {people
                                .filter((u) => !inProgramme.has(u.id))
                                .map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                        </SelectField>
                        <InputError message={member.errors.user_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="hm-start">
                            {t('Exposed since')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="hm-start"
                            value={member.data.started_on}
                            onChange={(v) => member.setData('started_on', v)}
                        />
                        <InputError message={member.errors.started_on} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={ending !== null}
                onOpenChange={(o) => !o && setEnding(null)}
                title="End exposure"
                description="The worker no longer has this exposure. Their records are kept."
                icon={UserMinus}
                submitLabel="End exposure"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (ending) {
                        end.put(
                            healthRoutes.members.end.url([
                                p.id,
                                ending.member_id,
                            ]),
                            {
                                preserveScroll: true,
                                onSuccess: () => setEnding(null),
                            },
                        );
                    }
                }}
                processing={end.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="hm-end">
                        {t('Last exposed on')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <DatePicker
                        id="hm-end"
                        value={end.data.ended_on}
                        onChange={(v) => end.setData('ended_on', v)}
                    />
                    <InputError message={end.errors.ended_on} />
                    <p className="text-xs text-muted-foreground">
                        {t('Consider an exit examination first.')}
                    </p>
                </div>
            </FormDialog>

            <FormDialog
                open={dialog === 'exam'}
                onOpenChange={(o) => !o && setDialog(null)}
                title="Record result"
                description={
                    p.hazard === 'noise'
                        ? 'The audiometric test centre’s result, as interpreted by the occupational health doctor.'
                        : 'The occupational health doctor’s finding.'
                }
                icon={Stethoscope}
                onSubmit={(e) => {
                    e.preventDefault();
                    exam.post(healthRoutes.exams.store.url(p.id), {
                        ...close,
                        forceFormData: true,
                    });
                }}
                processing={exam.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="he-user">
                            {t('Worker')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="he-user"
                            value={exam.data.user_id}
                            onChange={(e) =>
                                exam.setData('user_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select person')}</option>
                            {members.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={exam.errors.user_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="he-type">
                            {t('Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="he-type"
                            value={exam.data.type}
                            onChange={(e) =>
                                exam.setData('type', e.target.value)
                            }
                        >
                            {EXAM_TYPES.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {t(type.label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={exam.errors.type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="he-date">
                            {t('Examined on')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="he-date"
                            value={exam.data.examined_on}
                            onChange={(v) => exam.setData('examined_on', v)}
                        />
                        <InputError message={exam.errors.examined_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="he-outcome">
                            {t('Result')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="he-outcome"
                            value={exam.data.outcome}
                            onChange={(e) =>
                                exam.setData('outcome', e.target.value)
                            }
                        >
                            <option value="">{t('Select result')}</option>
                            {outcomes.map((o) => (
                                <option key={o} value={o}>
                                    {t(labelOf(HEALTH_OUTCOMES, o))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={exam.errors.outcome} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="he-provider">
                            {p.hazard === 'noise'
                                ? t('Audiometric test centre / doctor')
                                : t('Occupational health doctor')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="he-provider"
                            value={exam.data.provider}
                            onChange={(e) =>
                                exam.setData('provider', e.target.value)
                            }
                        />
                        <InputError message={exam.errors.provider} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="he-notes">
                            {t('Findings / restrictions')}
                        </Label>
                        <Textarea
                            id="he-notes"
                            rows={2}
                            value={exam.data.notes}
                            onChange={(e) =>
                                exam.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={exam.errors.notes} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="he-file">{t('Report')}</Label>
                        <Input
                            id="he-file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) =>
                                exam.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={exam.errors.file} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={informing !== null}
                onOpenChange={(o) => !o && setInforming(null)}
                title="Worker told"
                description="When the worker was told their result (within 21 days)."
                icon={Stethoscope}
                onSubmit={(e) => {
                    e.preventDefault();
                    if (informing) {
                        informed.put(
                            healthRoutes.exams.informed.url([
                                p.id,
                                informing.id,
                            ]),
                            {
                                preserveScroll: true,
                                onSuccess: () => setInforming(null),
                            },
                        );
                    }
                }}
                processing={informed.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="hi-date">
                        {t('Told on')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <DatePicker
                        id="hi-date"
                        value={informed.data.informed_on}
                        onChange={(v) => informed.setData('informed_on', v)}
                    />
                    <InputError message={informed.errors.informed_on} />
                </div>
            </FormDialog>

            <FormDialog
                open={notifying !== null}
                onOpenChange={(o) => !o && setNotifying(null)}
                title="DOSH notified"
                description="On the approved form, to the nearest DOSH office, within 7 days of receiving the result, with a copy of the latest report."
                icon={Stethoscope}
                onSubmit={(e) => {
                    e.preventDefault();
                    if (notifying) {
                        notified.put(
                            healthRoutes.exams.notified.url([
                                p.id,
                                notifying.id,
                            ]),
                            {
                                preserveScroll: true,
                                onSuccess: () => setNotifying(null),
                            },
                        );
                    }
                }}
                processing={notified.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="hn-date">
                            {t('Notified on')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="hn-date"
                            value={notified.data.dosh_notified_on}
                            onChange={(v) =>
                                notified.setData('dosh_notified_on', v)
                            }
                        />
                        <InputError
                            message={notified.errors.dosh_notified_on}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="hn-ref">
                            {t('Reference')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="hn-ref"
                            value={notified.data.dosh_reference}
                            onChange={(e) =>
                                notified.setData(
                                    'dosh_reference',
                                    e.target.value,
                                )
                            }
                        />
                        <InputError message={notified.errors.dosh_reference} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={dialog === 'reading'}
                onOpenChange={(o) => !o && setDialog(null)}
                title="Add reading"
                description={
                    p.hazard === 'noise'
                        ? 'A noise exposure measurement by the registered noise risk assessor.'
                        : 'An exposure measurement by a hygiene technician.'
                }
                icon={Gauge}
                onSubmit={(e) => {
                    e.preventDefault();
                    reading.post(healthRoutes.readings.store.url(p.id), {
                        ...close,
                        forceFormData: true,
                    });
                }}
                processing={reading.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="hr-date">
                            {t('Measured on')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="hr-date"
                            value={reading.data.measured_on}
                            onChange={(v) => reading.setData('measured_on', v)}
                        />
                        <InputError message={reading.errors.measured_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="hr-value">
                            {t('Value')} {p.unit && `(${p.unit})`}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="hr-value"
                            inputMode="decimal"
                            value={reading.data.value}
                            onChange={(e) =>
                                reading.setData('value', e.target.value)
                            }
                        />
                        <InputError message={reading.errors.value} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="hr-user">{t('Worn by')}</Label>
                        <SelectField
                            id="hr-user"
                            value={reading.data.user_id}
                            onChange={(e) =>
                                reading.setData('user_id', e.target.value)
                            }
                        >
                            <option value="">{t('Area reading')}</option>
                            {members.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="hr-assessor">
                            {t('Measured by')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="hr-assessor"
                            value={reading.data.assessor}
                            onChange={(e) =>
                                reading.setData('assessor', e.target.value)
                            }
                        />
                        <InputError message={reading.errors.assessor} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="hr-file">{t('Report')}</Label>
                        <Input
                            id="hr-file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) =>
                                reading.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={reading.errors.file} />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

ShowHealthProgramme.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Health surveillance', href: healthRoutes.index() },
    ],
};
