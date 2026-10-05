import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleAlert,
    CornerDownRight,
    FilePen,
    ListChecks,
    Lock,
    Plus,
    Save,
    SquarePen,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ActionsPanel } from '@/components/actions-panel';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DateTimePicker, localDateTime } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PhotoGallery } from '@/components/photo-picker';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import {
    CLASSIFICATIONS,
    ILLNESS_TYPES,
    INCIDENT_TYPES,
    PERSON_ROLES,
    ROOT_CAUSE_CATEGORIES,
    TREATMENTS,
    labelOf,
} from '@/lib/hse';
import { dashboard } from '@/routes';
import incidentRoutes from '@/routes/incidents';
import observationRoutes from '@/routes/observations';
import photoRoutes from '@/routes/photos';
import riskRoutes from '@/routes/risk-assessments';
import type { Action, Incident, IncidentPerson } from '@/types';

type Detail = Incident & {
    investigation_complete: boolean;
    site: {
        id: number;
        code: string;
        name: string;
        areas: { id: number; name: string }[];
    };
    creator: { id: number; name: string } | null;
    closer: { id: number; name: string } | null;
    observation: { id: number; number: string } | null;
    risk_assessment: {
        id: number;
        number: string;
        revision: number;
        title: string;
    } | null;
    people: IncidentPerson[];
    photos: { id: number }[];
    actions: Action[];
};

type User = { id: number; name: string };

const blankPerson = {
    user_id: '',
    name: '',
    job_title: '',
    role: 'injured',
    treatment: '',
    illness_type: '',
    privacy_case: false,
    body_part: '',
    injury_nature: '',
    days_lost: '',
    days_restricted: '',
};

const WHY_COUNT = 5;

export default function ShowIncident({
    incident: i,
    users,
    assessments,
}: {
    incident: Detail;
    users: User[];
    assessments: { id: number; number: string; title: string }[];
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const { errors } = usePage().props as { errors: Record<string, string> };
    const editable = can('edit-incidents') && i.status !== 'closed';

    const [editing, setEditing] = useState(false);
    const [personFor, setPersonFor] = useState<IncidentPerson | 'new' | null>(
        null,
    );
    const [removing, setRemoving] = useState<IncidentPerson | null>(null);
    const [closing, setClosing] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const details = useForm({
        type: i.type,
        title: i.title,
        area_id: String(i.area_id ?? ''),
        occurred_at: localDateTime(i.occurred_at),
        description: i.description,
        immediate_actions: i.immediate_actions ?? '',
        risk_assessment_id: String(i.risk_assessment_id ?? ''),
    });
    const person = useForm(blankPerson);
    const investigation = useForm({
        investigation_team: i.investigation_team ?? '',
        sequence_of_events: i.sequence_of_events ?? '',
        whys: Array.from({ length: WHY_COUNT }, (_, n) => i.whys?.[n] ?? ''),
        root_cause_category: i.root_cause_category ?? '',
        root_cause: i.root_cause ?? '',
        contributing_factors: i.contributing_factors ?? '',
    });

    const openPerson = (p: IncidentPerson | null) => {
        person.clearErrors();
        person.setData(
            p
                ? {
                      user_id: String(p.user_id ?? ''),
                      name: p.name,
                      job_title: p.job_title ?? '',
                      role: p.role,
                      treatment: p.treatment ?? '',
                      illness_type: p.illness_type ?? '',
                      privacy_case: p.privacy_case,
                      body_part: p.body_part ?? '',
                      injury_nature: p.injury_nature ?? '',
                      days_lost: String(p.days_lost || ''),
                      days_restricted: String(p.days_restricted || ''),
                  }
                : blankPerson,
        );
        setPersonFor(p ?? 'new');
    };

    const move = (status: string) =>
        router.put(
            incidentRoutes.transition(i.id),
            { status },
            { preserveScroll: true, onSuccess: () => setClosing(false) },
        );

    const facts: [string, ReactNode][] = [
        ['Type', t(labelOf(INCIDENT_TYPES, i.type))],
        [
            'Classification',
            <span key="c" className="flex flex-wrap items-center gap-2">
                <StatusBadge
                    status={i.classification}
                    label={t(labelOf(CLASSIFICATIONS, i.classification))}
                />
                {i.recordable && (
                    <span className="text-xs font-medium text-red-700 dark:text-red-400">
                        {t('Recordable')}
                    </span>
                )}
            </span>,
        ],
        ['Site', `${i.site.code} · ${i.site.name}`],
        ['Area', i.area?.name ?? '—'],
        ['Occurred', dateTime(i.occurred_at)],
        ['Reported by', i.creator?.name ?? '—'],
    ];

    if (i.observation) {
        facts.push([
            'Escalated from',
            <Link
                key="o"
                href={observationRoutes.show(i.observation.id)}
                className="inline-flex items-center gap-1 text-blue-600 hover:underline"
            >
                <CornerDownRight className="size-4" />
                {i.observation.number}
            </Link>,
        ]);
    }

    if (i.risk_assessment) {
        facts.push([
            'Risk assessment',
            <Link
                key="ra"
                href={riskRoutes.show(i.risk_assessment.id)}
                className="text-blue-600 hover:underline"
            >
                {i.risk_assessment.number} rev {i.risk_assessment.revision}
            </Link>,
        ]);
    }

    if (i.closed_at) {
        facts.push([
            'Closed',
            `${dateTime(i.closed_at)}${i.closer ? ` · ${i.closer.name}` : ''}`,
        ]);
    }

    const injured = person.data.role === 'injured';

    return (
        <>
            <Head title={i.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={i.number}
                    description={i.title}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={incidentRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {editable && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        details.clearErrors();
                                        setEditing(true);
                                    }}
                                >
                                    <SquarePen /> {t('Edit')}
                                </Button>
                            )}
                            {editable && i.status === 'under-investigation' && (
                                <Button
                                    variant="outline"
                                    onClick={() => move('actions-in-progress')}
                                >
                                    <ListChecks /> {t('Investigation done')}
                                </Button>
                            )}
                            {editable && (
                                <Button
                                    variant="outline"
                                    onClick={() => setClosing(true)}
                                >
                                    <Lock /> {t('Close')}
                                </Button>
                            )}
                            {can('delete-incidents') &&
                                i.actions.length === 0 && (
                                    <Button
                                        variant="outline"
                                        onClick={() => setDeleting(true)}
                                    >
                                        <Trash2 /> {t('Delete')}
                                    </Button>
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
                    <section className="grid content-start gap-4 rounded-xl border p-5">
                        <div className="flex items-center justify-between">
                            <IdBadge>{i.number}</IdBadge>
                            <StatusBadge status={i.status} />
                        </div>
                        <dl className="grid gap-3 text-sm">
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
                    </section>

                    <div className="grid content-start gap-6 lg:col-span-2">
                        <section className="grid gap-4 rounded-xl border p-5">
                            <div>
                                <h2 className="text-sm text-muted-foreground">
                                    {t('What happened')}
                                </h2>
                                <p className="mt-1 whitespace-pre-line">
                                    {i.description}
                                </p>
                            </div>
                            {i.immediate_actions && (
                                <div>
                                    <h2 className="text-sm text-muted-foreground">
                                        {t('Immediate actions taken')}
                                    </h2>
                                    <p className="mt-1 whitespace-pre-line">
                                        {i.immediate_actions}
                                    </p>
                                </div>
                            )}
                            {i.photos.length > 0 && (
                                <PhotoGallery
                                    photos={i.photos}
                                    url={(id) => photoRoutes.show.url(id)}
                                />
                            )}
                        </section>

                        <section className="grid gap-3 rounded-xl border p-5">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="font-medium">
                                    {t('People involved')}
                                </h2>
                                {editable && (
                                    <Button
                                        size="sm"
                                        onClick={() => openPerson(null)}
                                    >
                                        <Plus /> {t('Add person')}
                                    </Button>
                                )}
                            </div>
                            {i.people.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'Nobody added yet. Add anyone injured, plus witnesses.',
                                    )}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {i.people.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex flex-wrap items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div className="grid gap-1 text-sm">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-medium">
                                                        {p.name}
                                                    </span>
                                                    {p.job_title && (
                                                        <span className="text-muted-foreground">
                                                            {p.job_title}
                                                        </span>
                                                    )}
                                                    <StatusBadge
                                                        status={
                                                            p.treatment ??
                                                            'no-injury'
                                                        }
                                                        label={
                                                            p.role === 'injured'
                                                                ? t(
                                                                      labelOf(
                                                                          TREATMENTS,
                                                                          p.treatment ??
                                                                              '',
                                                                      ),
                                                                  )
                                                                : t(
                                                                      labelOf(
                                                                          PERSON_ROLES,
                                                                          p.role,
                                                                      ),
                                                                  )
                                                        }
                                                    />
                                                </div>
                                                {p.role === 'injured' && (
                                                    <div className="text-muted-foreground">
                                                        {[
                                                            p.injury_nature,
                                                            p.body_part,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') ||
                                                            t(
                                                                'Injury not described',
                                                            )}
                                                        {p.days_lost > 0 &&
                                                            ` · ${t(':n days lost', { n: p.days_lost })}`}
                                                        {p.days_restricted >
                                                            0 &&
                                                            ` · ${t(':n days restricted', { n: p.days_restricted })}`}
                                                    </div>
                                                )}
                                            </div>
                                            {editable && (
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t(
                                                            'Edit :name',
                                                            { name: p.name },
                                                        )}
                                                        onClick={() =>
                                                            openPerson(p)
                                                        }
                                                    >
                                                        <SquarePen />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t(
                                                            'Remove :name',
                                                            { name: p.name },
                                                        )}
                                                        onClick={() =>
                                                            setRemoving(p)
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <section className="grid gap-4 rounded-xl border p-5">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h2 className="font-medium">
                                        {t('Investigation')}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {i.recordable
                                            ? t(
                                                  'Required: this incident is recordable.',
                                              )
                                            : t(
                                                  'Ask "why?" until you reach the cause that, removed, stops it happening again.',
                                              )}
                                    </p>
                                </div>
                                {i.investigation_complete && (
                                    <StatusBadge
                                        status="verified"
                                        label={t('Complete')}
                                    />
                                )}
                            </div>
                            <form
                                noValidate
                                className="grid gap-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    investigation.put(
                                        incidentRoutes.investigate.url(i.id),
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <fieldset
                                    disabled={!editable}
                                    className="grid gap-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="inv-team">
                                            {t('Investigation team')}
                                        </Label>
                                        <Input
                                            id="inv-team"
                                            placeholder={t('Names and roles')}
                                            value={
                                                investigation.data
                                                    .investigation_team
                                            }
                                            onChange={(e) =>
                                                investigation.setData(
                                                    'investigation_team',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="inv-sequence">
                                            {t('Sequence of events')}
                                        </Label>
                                        <Textarea
                                            id="inv-sequence"
                                            rows={4}
                                            value={
                                                investigation.data
                                                    .sequence_of_events
                                            }
                                            onChange={(e) =>
                                                investigation.setData(
                                                    'sequence_of_events',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>{t('5 Whys')}</Label>
                                        <ol className="grid gap-2">
                                            {investigation.data.whys.map(
                                                (why, n) => (
                                                    <li
                                                        key={n}
                                                        className="flex items-center gap-2"
                                                    >
                                                        <span className="w-14 shrink-0 text-sm text-muted-foreground">
                                                            {t('Why :n', {
                                                                n: n + 1,
                                                            })}
                                                        </span>
                                                        <Input
                                                            aria-label={t(
                                                                'Why :n',
                                                                { n: n + 1 },
                                                            )}
                                                            value={why}
                                                            onChange={(e) =>
                                                                investigation.setData(
                                                                    'whys',
                                                                    investigation.data.whys.map(
                                                                        (
                                                                            w,
                                                                            m,
                                                                        ) =>
                                                                            m ===
                                                                            n
                                                                                ? e
                                                                                      .target
                                                                                      .value
                                                                                : w,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                    </li>
                                                ),
                                            )}
                                        </ol>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-[16rem_1fr]">
                                        <div className="grid content-start gap-2">
                                            <Label htmlFor="inv-category">
                                                {t('Root cause category')}
                                            </Label>
                                            <SelectField
                                                id="inv-category"
                                                value={
                                                    investigation.data
                                                        .root_cause_category
                                                }
                                                onChange={(e) =>
                                                    investigation.setData(
                                                        'root_cause_category',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    {t('Select category')}
                                                </option>
                                                {ROOT_CAUSE_CATEGORIES.map(
                                                    (c) => (
                                                        <option
                                                            key={c.value}
                                                            value={c.value}
                                                        >
                                                            {t(c.label)}
                                                        </option>
                                                    ),
                                                )}
                                            </SelectField>
                                            <InputError
                                                message={
                                                    investigation.errors
                                                        .root_cause_category
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="inv-root">
                                                {t('Root cause')}
                                            </Label>
                                            <Textarea
                                                id="inv-root"
                                                rows={2}
                                                value={
                                                    investigation.data
                                                        .root_cause
                                                }
                                                onChange={(e) =>
                                                    investigation.setData(
                                                        'root_cause',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="inv-factors">
                                            {t('Contributing factors')}
                                        </Label>
                                        <Textarea
                                            id="inv-factors"
                                            rows={2}
                                            value={
                                                investigation.data
                                                    .contributing_factors
                                            }
                                            onChange={(e) =>
                                                investigation.setData(
                                                    'contributing_factors',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </fieldset>
                                {editable && (
                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            disabled={investigation.processing}
                                        >
                                            <Save /> {t('Save investigation')}
                                        </Button>
                                    </div>
                                )}
                            </form>
                        </section>

                        <ActionsPanel
                            actions={i.actions}
                            owners={users}
                            sourceNumber={i.number}
                            store={incidentRoutes.actions.store(i.id)}
                            open={i.status !== 'closed'}
                            emptyText="No actions yet. Raise one for each fix the investigation calls for."
                        />
                    </div>
                </div>
            </div>

            <FormDialog
                open={editing}
                onOpenChange={setEditing}
                title="Edit incident"
                description="Correct the details. People, investigation and actions are edited on the page."
                icon={FilePen}
                onSubmit={(e) => {
                    e.preventDefault();
                    details.transform((data) => ({
                        ...data,
                        occurred_at: new Date(data.occurred_at).toISOString(),
                    }));
                    details.put(incidentRoutes.update.url(i.id), {
                        preserveScroll: true,
                        onSuccess: () => setEditing(false),
                    });
                }}
                processing={details.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="edit-type">
                            {t('Type')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="edit-type"
                            value={details.data.type}
                            onChange={(e) =>
                                details.setData('type', e.target.value)
                            }
                        >
                            {INCIDENT_TYPES.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {t(type.label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={details.errors.type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-when">
                            {t('When')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DateTimePicker
                            id="edit-when"
                            value={details.data.occurred_at}
                            onChange={(value) =>
                                details.setData('occurred_at', value)
                            }
                        />
                        <InputError message={details.errors.occurred_at} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="edit-title"
                            value={details.data.title}
                            onChange={(e) =>
                                details.setData('title', e.target.value)
                            }
                        />
                        <InputError message={details.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-area">{t('Area')}</Label>
                        <SelectField
                            id="edit-area"
                            value={details.data.area_id}
                            onChange={(e) =>
                                details.setData('area_id', e.target.value)
                            }
                        >
                            <option value="">{t('Not sure / other')}</option>
                            {i.site.areas.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={details.errors.area_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="edit-ra">
                            {t('Risk assessment that covered this work')}
                        </Label>
                        <SelectField
                            id="edit-ra"
                            value={details.data.risk_assessment_id}
                            onChange={(e) =>
                                details.setData(
                                    'risk_assessment_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">{t('None / not known')}</option>
                            {assessments.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.number} · {a.title}
                                </option>
                            ))}
                        </SelectField>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Linking it flags the assessment for review: its controls did not prevent this.',
                            )}
                        </p>
                        <InputError
                            message={details.errors.risk_assessment_id}
                        />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="edit-description">
                            {t('What happened?')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="edit-description"
                            rows={4}
                            value={details.data.description}
                            onChange={(e) =>
                                details.setData('description', e.target.value)
                            }
                        />
                        <InputError message={details.errors.description} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="edit-immediate">
                            {t('Immediate actions taken')}
                        </Label>
                        <Textarea
                            id="edit-immediate"
                            rows={2}
                            value={details.data.immediate_actions}
                            onChange={(e) =>
                                details.setData(
                                    'immediate_actions',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={personFor !== null}
                onOpenChange={(open) => !open && setPersonFor(null)}
                title={personFor === 'new' ? 'Add person' : 'Edit person'}
                description="Anyone injured, plus witnesses. The incident's classification follows the worst treatment given."
                icon={UserPlus}
                onSubmit={(e) => {
                    e.preventDefault();
                    person.submit(
                        personFor === 'new' || personFor === null
                            ? incidentRoutes.people.store(i.id)
                            : incidentRoutes.people.update([
                                  i.id,
                                  personFor.id,
                              ]),
                        {
                            preserveScroll: true,
                            onSuccess: () => setPersonFor(null),
                        },
                    );
                }}
                processing={person.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label id="person-role-label">{t('Role')}</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            aria-labelledby="person-role-label"
                            value={person.data.role}
                            onValueChange={(value) =>
                                value &&
                                person.setData((data) => ({
                                    ...data,
                                    role: value,
                                    treatment:
                                        value === 'injured'
                                            ? data.treatment
                                            : '',
                                }))
                            }
                            className="w-full"
                        >
                            {PERSON_ROLES.map((r) => (
                                <ToggleGroupItem
                                    key={r.value}
                                    value={r.value}
                                    className="flex-1 data-[state=on]:bg-green-50 data-[state=on]:text-green-800 dark:data-[state=on]:bg-green-950 dark:data-[state=on]:text-green-200"
                                >
                                    {t(r.label)}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <InputError message={person.errors.role} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="person-user">{t('Staff member')}</Label>
                        <SelectField
                            id="person-user"
                            value={person.data.user_id}
                            onChange={(e) =>
                                person.setData('user_id', e.target.value)
                            }
                        >
                            <option value="">
                                {t('Not a user (enter a name)')}
                            </option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="person-name">
                            {t('Name')}
                            {!person.data.user_id && (
                                <span className="text-destructive">*</span>
                            )}
                        </Label>
                        <Input
                            id="person-name"
                            disabled={person.data.user_id !== ''}
                            placeholder={
                                person.data.user_id
                                    ? t('Taken from the user')
                                    : undefined
                            }
                            value={person.data.user_id ? '' : person.data.name}
                            onChange={(e) =>
                                person.setData('name', e.target.value)
                            }
                        />
                        <InputError message={person.errors.name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="person-job">{t('Job title')}</Label>
                        <Input
                            id="person-job"
                            value={person.data.job_title}
                            onChange={(e) =>
                                person.setData('job_title', e.target.value)
                            }
                        />
                    </div>
                    {injured && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="person-treatment">
                                    {t('Treatment')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <SelectField
                                    id="person-treatment"
                                    value={person.data.treatment}
                                    onChange={(e) =>
                                        person.setData(
                                            'treatment',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Select treatment')}
                                    </option>
                                    {TREATMENTS.map((tr) => (
                                        <option key={tr.value} value={tr.value}>
                                            {t(tr.label)}
                                        </option>
                                    ))}
                                </SelectField>
                                <InputError message={person.errors.treatment} />
                            </div>
                            {i.type === 'illness' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="person-illness">
                                        {t('Type of illness')}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <SelectField
                                        id="person-illness"
                                        value={person.data.illness_type}
                                        onChange={(e) =>
                                            person.setData(
                                                'illness_type',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('Select type')}
                                        </option>
                                        {ILLNESS_TYPES.map((it) => (
                                            <option
                                                key={it.value}
                                                value={it.value}
                                            >
                                                {t(it.label)}
                                            </option>
                                        ))}
                                    </SelectField>
                                    <InputError
                                        message={person.errors.illness_type}
                                    />
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="person-nature">
                                    {t('Nature of injury')}
                                </Label>
                                <Input
                                    id="person-nature"
                                    placeholder={t('e.g. laceration, fracture')}
                                    value={person.data.injury_nature}
                                    onChange={(e) =>
                                        person.setData(
                                            'injury_nature',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="person-body">
                                    {t('Body part')}
                                </Label>
                                <Input
                                    id="person-body"
                                    placeholder={t('e.g. left hand')}
                                    value={person.data.body_part}
                                    onChange={(e) =>
                                        person.setData(
                                            'body_part',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="person-lost">
                                        {t('Days lost')}
                                        {person.data.treatment ===
                                            'lost-time' && (
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        )}
                                    </Label>
                                    <Input
                                        id="person-lost"
                                        inputMode="numeric"
                                        value={person.data.days_lost}
                                        onChange={(e) =>
                                            person.setData(
                                                'days_lost',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={person.errors.days_lost}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="person-restricted">
                                        {t('Days restricted')}
                                    </Label>
                                    <Input
                                        id="person-restricted"
                                        inputMode="numeric"
                                        value={person.data.days_restricted}
                                        onChange={(e) =>
                                            person.setData(
                                                'days_restricted',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={person.errors.days_restricted}
                                    />
                                </div>
                            </div>
                            <Label className="flex items-start gap-3 font-normal sm:col-span-2">
                                <Checkbox
                                    className="mt-0.5"
                                    checked={person.data.privacy_case}
                                    onCheckedChange={(checked) =>
                                        person.setData(
                                            'privacy_case',
                                            checked === true,
                                        )
                                    }
                                />
                                <span>
                                    {t('Privacy case')}
                                    <span className="block text-xs text-muted-foreground">
                                        {t(
                                            'The name is left off the injury log (e.g. intimate body part, sexual assault, mental illness, infectious disease, needlestick, or at the person’s request).',
                                        )}
                                    </span>
                                </span>
                            </Label>
                        </>
                    )}
                </div>
            </FormDialog>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Remove person"
                description={t(
                    'Remove :name from this incident? The classification is worked out again.',
                    { name: removing?.name ?? '' },
                )}
                confirmLabel="Remove"
                onConfirm={() =>
                    removing &&
                    router.delete(
                        incidentRoutes.people.destroy([i.id, removing.id]),
                        {
                            preserveScroll: true,
                            onSuccess: () => setRemoving(null),
                        },
                    )
                }
            />

            <ConfirmDialog
                open={closing}
                onOpenChange={setClosing}
                title="Close incident"
                description={
                    i.recordable
                        ? 'Every action must be verified, and this recordable incident must be investigated, before it can close.'
                        : 'Every action must be verified before the incident can close.'
                }
                confirmLabel="Close"
                tone="default"
                onConfirm={() => move('closed')}
            />

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                description="This incident, its people and its photos will be permanently deleted."
                onConfirm={() => router.delete(incidentRoutes.destroy(i.id))}
            />
        </>
    );
}

ShowIncident.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Incidents', href: incidentRoutes.index() },
    ],
};
