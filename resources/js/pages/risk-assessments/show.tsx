import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    CircleAlert,
    CopyPlus,
    FilePen,
    Plus,
    ShieldAlert,
    SquarePen,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { RiskMatrix, RiskScore } from '@/components/risk-matrix';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import {
    CLASSIFICATIONS,
    LIKELIHOOD,
    RESIDUAL_LIMIT,
    RISK_TYPES,
    SEVERITY,
    labelOf,
} from '@/lib/hse';
import { dashboard } from '@/routes';
import incidentRoutes from '@/routes/incidents';
import riskRoutes from '@/routes/risk-assessments';
import type { RiskAssessment, RiskHazard } from '@/types';

type Detail = RiskAssessment & {
    review_overdue: boolean;
    site: {
        id: number;
        code: string;
        name: string;
        areas: { id: number; name: string }[];
    };
    hazards: RiskHazard[];
    creator: { id: number; name: string } | null;
    approver: { id: number; name: string } | null;
    incidents: {
        id: number;
        number: string;
        title: string;
        classification: string;
    }[];
};

type Revision = {
    id: number;
    revision: number;
    status: string;
    approved_at: string | null;
};

const blankHazard = {
    hazard: '',
    who_at_risk: '',
    existing_controls: '',
    likelihood: '3',
    severity: '3',
    additional_controls: '',
    residual_likelihood: '2',
    residual_severity: '3',
};

function ScoreSelects({
    heading,
    prefix,
    likelihood,
    severity,
    onChange,
    errors,
}: {
    heading: string;
    prefix: string;
    likelihood: string;
    severity: string;
    onChange: (field: 'likelihood' | 'severity', value: string) => void;
    errors: (string | undefined)[];
}) {
    const { t } = useTranslation();

    return (
        <fieldset className="grid grid-cols-[1fr_1fr_auto] items-end gap-3">
            <legend className="mb-2 text-sm font-medium text-muted-foreground">
                {t(heading)}
            </legend>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-l`}>{t('Likelihood')}</Label>
                <SelectField
                    id={`${prefix}-l`}
                    value={likelihood}
                    onChange={(e) => onChange('likelihood', e.target.value)}
                >
                    {LIKELIHOOD.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.value} · {t(o.label)}
                        </option>
                    ))}
                </SelectField>
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-s`}>{t('Severity')}</Label>
                <SelectField
                    id={`${prefix}-s`}
                    value={severity}
                    onChange={(e) => onChange('severity', e.target.value)}
                >
                    {SEVERITY.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.value} · {t(o.label)}
                        </option>
                    ))}
                </SelectField>
            </div>
            <div className="pb-2">
                <RiskScore score={Number(likelihood) * Number(severity)} />
            </div>
            {errors.filter(Boolean).map((e) => (
                <InputError key={e} message={e} className="col-span-3" />
            ))}
        </fieldset>
    );
}

export default function ShowRiskAssessment({
    assessment: a,
    revisions,
}: {
    assessment: Detail;
    revisions: Revision[];
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const { errors, auth } = usePage().props as {
        errors: Record<string, string>;
        auth: { user: { id: number } };
    };
    const draft = a.status === 'draft';
    const editable = draft && can('edit-risk-assessments');

    const [editing, setEditing] = useState(false);
    const [hazardFor, setHazardFor] = useState<RiskHazard | 'new' | null>(null);
    const [removing, setRemoving] = useState<RiskHazard | null>(null);
    const [deleting, setDeleting] = useState(false);

    const details = useForm({
        type: a.type,
        title: a.title,
        area_id: String(a.area_id ?? ''),
        activity: a.activity,
        review_due_on: a.review_due_on,
    });
    const hazard = useForm(blankHazard);
    const residual =
        Number(hazard.data.residual_likelihood) *
        Number(hazard.data.residual_severity);

    const openHazard = (h: RiskHazard | null) => {
        hazard.clearErrors();
        hazard.setData(
            h
                ? {
                      hazard: h.hazard,
                      who_at_risk: h.who_at_risk ?? '',
                      existing_controls: h.existing_controls ?? '',
                      likelihood: String(h.likelihood),
                      severity: String(h.severity),
                      additional_controls: h.additional_controls ?? '',
                      residual_likelihood: String(h.residual_likelihood),
                      residual_severity: String(h.residual_severity),
                  }
                : blankHazard,
        );
        setHazardFor(h ?? 'new');
    };

    const put = (route: { url: string; method: 'put' }) =>
        router.put(route.url, {}, { preserveScroll: true });

    const facts: [string, ReactNode][] = [
        ['Type', t(labelOf(RISK_TYPES, a.type))],
        ['Site', `${a.site.code} · ${a.site.name}`],
        ['Area', a.area?.name ?? t('Whole site')],
        [
            'Review by',
            <DateCell key="r" value={a.review_due_on}>
                {a.review_overdue && <StatusBadge status="overdue" />}
            </DateCell>,
        ],
        ['Written by', a.creator?.name ?? '—'],
    ];

    if (a.approved_at) {
        facts.push([
            'Approved',
            `${dateTime(a.approved_at)}${a.approver ? ` · ${a.approver.name}` : ''}`,
        ]);
    }

    return (
        <>
            <Head title={`${a.number} rev ${a.revision}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${a.number} · ${t('Rev :r', { r: a.revision })}`}
                    description={a.title}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={riskRoutes.index()}>
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
                            {draft &&
                                can('approve-risk-assessments') &&
                                a.created_by !== auth.user.id && (
                                    <Button
                                        onClick={() =>
                                            put(riskRoutes.approve(a.id))
                                        }
                                    >
                                        <BadgeCheck /> {t('Approve')}
                                    </Button>
                                )}
                            {a.status === 'approved' &&
                                can('edit-risk-assessments') && (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                riskRoutes.revise.url(a.id),
                                            )
                                        }
                                    >
                                        <CopyPlus /> {t('New revision')}
                                    </Button>
                                )}
                            {draft && can('delete-risk-assessments') && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDeleting(true)}
                                >
                                    <Trash2 /> {t('Delete draft')}
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

                {a.review_required && (
                    <div
                        role="status"
                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100"
                    >
                        <span className="flex items-center gap-2">
                            <TriangleAlert className="size-4 shrink-0" />
                            {t('Review required: :reason', {
                                reason: a.review_reason ?? '',
                            })}
                        </span>
                        {can('approve-risk-assessments') &&
                            a.status === 'approved' && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        put(riskRoutes.clearReview(a.id))
                                    }
                                >
                                    {t('Reviewed, no change needed')}
                                </Button>
                            )}
                    </div>
                )}

                {a.status === 'superseded' && (
                    <div className="rounded-lg border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
                        {t(
                            'This revision has been superseded. See the latest revision below.',
                        )}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="grid content-start gap-6">
                        <section className="grid content-start gap-4 rounded-xl border p-5">
                            <div className="flex items-center justify-between">
                                <IdBadge>{a.number}</IdBadge>
                                <StatusBadge status={a.status} />
                            </div>
                            <dl className="grid gap-3 text-sm">
                                {facts.map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="grid grid-cols-[7rem_1fr] gap-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {t(label)}
                                        </dt>
                                        <dd className="font-medium">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section className="grid gap-2 rounded-xl border p-5">
                            <h2 className="font-medium">{t('Revisions')}</h2>
                            <ul className="grid gap-1 text-sm">
                                {revisions.map((r) => (
                                    <li
                                        key={r.id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        {r.id === a.id ? (
                                            <span className="font-medium">
                                                {t('Rev :r', {
                                                    r: r.revision,
                                                })}{' '}
                                                ({t('this one')})
                                            </span>
                                        ) : (
                                            <Link
                                                href={riskRoutes.show(r.id)}
                                                className="text-blue-600 hover:underline"
                                            >
                                                {t('Rev :r', {
                                                    r: r.revision,
                                                })}
                                            </Link>
                                        )}
                                        <StatusBadge status={r.status} />
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </div>

                    <div className="grid content-start gap-6 lg:col-span-2">
                        <section className="grid gap-4 rounded-xl border p-5 sm:grid-cols-2">
                            <div>
                                <h2 className="text-sm text-muted-foreground">
                                    {t('Activity')}
                                </h2>
                                <p className="mt-1 whitespace-pre-line">
                                    {a.activity}
                                </p>
                            </div>
                            <RiskMatrix hazards={a.hazards} />
                        </section>

                        <section className="grid gap-3 rounded-xl border p-5">
                            <div className="flex items-center justify-between gap-2">
                                <div>
                                    <h2 className="font-medium">
                                        {t('Hazards')}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'Approval needs every residual score below :n.',
                                            { n: RESIDUAL_LIMIT },
                                        )}
                                    </p>
                                </div>
                                {editable && (
                                    <Button
                                        size="sm"
                                        onClick={() => openHazard(null)}
                                    >
                                        <Plus /> {t('Add hazard')}
                                    </Button>
                                )}
                            </div>
                            {a.hazards.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No hazards yet.')}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {a.hazards.map((h) => (
                                        <li
                                            key={h.id}
                                            className="grid gap-2 py-3 first:pt-0 last:pb-0 sm:grid-cols-[1fr_auto]"
                                        >
                                            <div className="grid gap-1 text-sm">
                                                <div className="font-medium">
                                                    {h.hazard}
                                                    {h.who_at_risk && (
                                                        <span className="font-normal text-muted-foreground">
                                                            {' '}
                                                            · {h.who_at_risk}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <RiskScore
                                                        score={h.initial_score}
                                                    />
                                                    <span className="text-muted-foreground">
                                                        →
                                                    </span>
                                                    <RiskScore
                                                        score={h.residual_score}
                                                    />
                                                </div>
                                                {h.existing_controls && (
                                                    <p>
                                                        <span className="text-muted-foreground">
                                                            {t(
                                                                'In place:',
                                                            )}{' '}
                                                        </span>
                                                        {h.existing_controls}
                                                    </p>
                                                )}
                                                {h.additional_controls && (
                                                    <p>
                                                        <span className="text-muted-foreground">
                                                            {t('Added:')}{' '}
                                                        </span>
                                                        {h.additional_controls}
                                                    </p>
                                                )}
                                            </div>
                                            {editable && (
                                                <div className="flex gap-1 self-start">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t('Edit')}
                                                        onClick={() =>
                                                            openHazard(h)
                                                        }
                                                    >
                                                        <SquarePen />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t('Remove')}
                                                        onClick={() =>
                                                            setRemoving(h)
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

                        {a.incidents.length > 0 && (
                            <section className="grid gap-3 rounded-xl border p-5">
                                <h2 className="font-medium">
                                    {t('Incidents under this assessment')}
                                </h2>
                                <ul className="grid gap-2 text-sm">
                                    {a.incidents.map((i) => (
                                        <li
                                            key={i.id}
                                            className="flex flex-wrap items-center gap-2"
                                        >
                                            <Link
                                                href={incidentRoutes.show(i.id)}
                                                className="text-blue-600 hover:underline"
                                            >
                                                {i.number}
                                            </Link>
                                            {i.title}
                                            <StatusBadge
                                                status={i.classification}
                                                label={t(
                                                    labelOf(
                                                        CLASSIFICATIONS,
                                                        i.classification,
                                                    ),
                                                )}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </div>
                </div>
            </div>

            <FormDialog
                open={hazardFor !== null}
                onOpenChange={(open) => !open && setHazardFor(null)}
                title={hazardFor === 'new' ? 'Add hazard' : 'Edit hazard'}
                description="Score it as it is now, then again with the extra controls in place."
                icon={ShieldAlert}
                onSubmit={(e) => {
                    e.preventDefault();
                    hazard.submit(
                        hazardFor === 'new' || hazardFor === null
                            ? riskRoutes.hazards.store(a.id)
                            : riskRoutes.hazards.update([a.id, hazardFor.id]),
                        {
                            preserveScroll: true,
                            onSuccess: () => setHazardFor(null),
                        },
                    );
                }}
                processing={hazard.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="hz-hazard">
                                {t('Hazard')}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="hz-hazard"
                                placeholder={t('e.g. Fall from height')}
                                value={hazard.data.hazard}
                                onChange={(e) =>
                                    hazard.setData('hazard', e.target.value)
                                }
                            />
                            <InputError message={hazard.errors.hazard} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="hz-who">
                                {t('Who is at risk')}
                            </Label>
                            <Input
                                id="hz-who"
                                placeholder={t('e.g. Scaffolders, passers-by')}
                                value={hazard.data.who_at_risk}
                                onChange={(e) =>
                                    hazard.setData(
                                        'who_at_risk',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="hz-existing">
                            {t('Controls already in place')}
                        </Label>
                        <Textarea
                            id="hz-existing"
                            rows={2}
                            value={hazard.data.existing_controls}
                            onChange={(e) =>
                                hazard.setData(
                                    'existing_controls',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                    <ScoreSelects
                        heading="Before additional controls"
                        prefix="hz-initial"
                        likelihood={hazard.data.likelihood}
                        severity={hazard.data.severity}
                        onChange={(field, value) =>
                            hazard.setData(field, value)
                        }
                        errors={[
                            hazard.errors.likelihood,
                            hazard.errors.severity,
                        ]}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="hz-additional">
                            {t('Additional controls')}
                        </Label>
                        <Textarea
                            id="hz-additional"
                            rows={2}
                            placeholder={t(
                                'Elimination and engineering first; PPE last.',
                            )}
                            value={hazard.data.additional_controls}
                            onChange={(e) =>
                                hazard.setData(
                                    'additional_controls',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                    <ScoreSelects
                        heading="After additional controls"
                        prefix="hz-residual"
                        likelihood={hazard.data.residual_likelihood}
                        severity={hazard.data.residual_severity}
                        onChange={(field, value) =>
                            hazard.setData(
                                field === 'likelihood'
                                    ? 'residual_likelihood'
                                    : 'residual_severity',
                                value,
                            )
                        }
                        errors={[
                            hazard.errors.residual_likelihood,
                            hazard.errors.residual_severity,
                        ]}
                    />
                    {residual >= RESIDUAL_LIMIT && (
                        <p className="flex items-center gap-2 text-sm text-red-700 dark:text-red-400">
                            <TriangleAlert className="size-4 shrink-0" />
                            {t(
                                'Still :n or above after controls: the assessment cannot be approved like this.',
                                { n: RESIDUAL_LIMIT },
                            )}
                        </p>
                    )}
                </div>
            </FormDialog>

            <FormDialog
                open={editing}
                onOpenChange={setEditing}
                title="Edit assessment"
                description="Only a draft can be changed."
                icon={FilePen}
                onSubmit={(e) => {
                    e.preventDefault();
                    details.put(riskRoutes.update.url(a.id), {
                        preserveScroll: true,
                        onSuccess: () => setEditing(false),
                    });
                }}
                processing={details.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ed-title">
                            {t('Title')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="ed-title"
                            value={details.data.title}
                            onChange={(e) =>
                                details.setData('title', e.target.value)
                            }
                        />
                        <InputError message={details.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ed-type">{t('Type')}</Label>
                        <SelectField
                            id="ed-type"
                            value={details.data.type}
                            onChange={(e) =>
                                details.setData('type', e.target.value)
                            }
                        >
                            {RISK_TYPES.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {t(r.label)}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ed-review">
                            {t('Review by')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="ed-review"
                            value={details.data.review_due_on}
                            onChange={(value) =>
                                details.setData('review_due_on', value)
                            }
                        />
                        <InputError message={details.errors.review_due_on} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ed-area">{t('Area')}</Label>
                        <SelectField
                            id="ed-area"
                            value={details.data.area_id}
                            onChange={(e) =>
                                details.setData('area_id', e.target.value)
                            }
                        >
                            <option value="">{t('Whole site')}</option>
                            {a.site.areas.map((area) => (
                                <option key={area.id} value={area.id}>
                                    {area.name}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="ed-activity">
                            {t('Activity')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="ed-activity"
                            rows={3}
                            value={details.data.activity}
                            onChange={(e) =>
                                details.setData('activity', e.target.value)
                            }
                        />
                        <InputError message={details.errors.activity} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Remove hazard"
                description={t('Remove ":hazard" from this assessment?', {
                    hazard: removing?.hazard ?? '',
                })}
                confirmLabel="Remove"
                onConfirm={() =>
                    removing &&
                    router.delete(
                        riskRoutes.hazards.destroy([a.id, removing.id]),
                        {
                            preserveScroll: true,
                            onSuccess: () => setRemoving(null),
                        },
                    )
                }
            />

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title="Delete draft"
                description="This draft revision and its hazards will be deleted. Approved revisions are kept."
                onConfirm={() => router.delete(riskRoutes.destroy(a.id))}
            />
        </>
    );
}

ShowRiskAssessment.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Risk assessments', href: riskRoutes.index() },
    ],
};
