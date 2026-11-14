import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    CircleAlert,
    ClipboardPen,
    Lock,
    MapPin,
    Siren,
    Trash2,
    Trophy,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ActionsPanel } from '@/components/actions-panel';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PhotoGallery } from '@/components/photo-picker';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { OBSERVATION_TOPICS, OBSERVATION_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import incidentRoutes from '@/routes/incidents';
import observationRoutes from '@/routes/observations';
import photoRoutes from '@/routes/photos';
import type { Action, Observation } from '@/types';

type Detail = Observation & {
    incident: { id: number; number: string } | null;
    photos: { id: number }[];
    actions: Action[];
};

type Step = 'review' | 'dismiss' | 'recognise';

export default function ShowObservation({
    observation: o,
    owners,
    contractors,
}: {
    observation: Detail;
    owners: { id: number; name: string }[];
    contractors: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const [closing, setClosing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [step, setStep] = useState<Step | null>(null);
    const { errors } = usePage().props as { errors: Record<string, string> };
    const review = useForm({
        topic: o.topic ?? '',
        contractor_id: String(o.contractor?.id ?? ''),
    });
    const dismissal = useForm({ reason: '' });
    const recognition = useForm({ note: '' });
    const live = o.status === 'open' || o.status === 'actioned';
    const openStep = (next: Step) => {
        review.clearErrors();
        dismissal.clearErrors();
        recognition.clearErrors();
        setStep(next);
    };
    const finish = { preserveScroll: true, onSuccess: () => setStep(null) };
    const openActions = o.actions.filter((a) => a.status !== 'verified');

    const facts: [string, ReactNode][] = [
        ['Type', t(labelOf(OBSERVATION_TYPES, o.type))],
        ['Topic', o.topic ? t(labelOf(OBSERVATION_TOPICS, o.topic)) : '—'],
        ['Crew', o.contractor?.name ?? '—'],
        ['Potential', <StatusBadge key="p" status={o.potential} />],
        ['Site', `${o.site.code} · ${o.site.name}`],
        ['Area', o.area?.name ?? '—'],
        ['Observed', dateTime(o.observed_at)],
        [
            'Reported by',
            o.reporter?.name ?? (
                <span key="r" className="italic">
                    {t('Anonymous')}
                </span>
            ),
        ],
        [
            'Location',
            o.latitude ? (
                <a
                    key="l"
                    href={`https://www.openstreetmap.org/?mlat=${o.latitude}&mlon=${o.longitude}#map=19/${o.latitude}/${o.longitude}`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                >
                    <MapPin className="size-4" />
                    {t('Open map')}
                </a>
            ) : (
                '—'
            ),
        ],
    ];

    if (o.closed_at) {
        facts.push([
            o.status === 'dismissed' ? 'Dismissed' : 'Closed',
            `${dateTime(o.closed_at)}${o.closer ? ` · ${o.closer.name}` : ''}`,
        ]);
    }

    return (
        <>
            <Head title={o.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={o.number}
                    description={t(labelOf(OBSERVATION_TYPES, o.type))}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={observationRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {o.incident ? (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={incidentRoutes.show(
                                            o.incident.id,
                                        )}
                                    >
                                        <Siren /> {o.incident.number}
                                    </Link>
                                </Button>
                            ) : (
                                can('create-incidents') &&
                                o.type !== 'positive' && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={incidentRoutes.create({
                                                query: { observation: o.id },
                                            })}
                                        >
                                            <Siren />{' '}
                                            {t('Escalate to incident')}
                                        </Link>
                                    </Button>
                                )
                            )}
                            {can('edit-observations') && live && (
                                <Button
                                    variant="outline"
                                    onClick={() => openStep('review')}
                                >
                                    <ClipboardPen /> {t('Review')}
                                </Button>
                            )}
                            {can('recognise-observations') &&
                                o.reporter &&
                                o.status !== 'dismissed' &&
                                !o.recognised_for && (
                                    <Button
                                        variant="outline"
                                        onClick={() => openStep('recognise')}
                                    >
                                        <Trophy />{' '}
                                        {t('Observation of the month')}
                                    </Button>
                                )}
                            {can('edit-observations') &&
                                o.status === 'open' &&
                                o.actions.length === 0 && (
                                    <Button
                                        variant="outline"
                                        onClick={() => openStep('dismiss')}
                                    >
                                        <Ban /> {t('Dismiss')}
                                    </Button>
                                )}
                            {can('edit-observations') && live && (
                                <Button
                                    variant="outline"
                                    onClick={() => setClosing(true)}
                                    disabled={openActions.length > 0}
                                    title={
                                        openActions.length > 0
                                            ? t(
                                                  'Every action must be verified first.',
                                              )
                                            : undefined
                                    }
                                >
                                    <Lock /> {t('Close')}
                                </Button>
                            )}
                            {can('delete-observations') &&
                                o.actions.length === 0 && (
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

                {o.recognised_for && (
                    <div className="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                        <Trophy className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <div className="font-medium">
                                {t('Observation of the month: :month', {
                                    month: new Date(
                                        `${o.recognised_for}T00:00:00`,
                                    ).toLocaleDateString('en-GB', {
                                        month: 'long',
                                        year: 'numeric',
                                    }),
                                })}
                            </div>
                            {o.recognition_note}
                        </div>
                    </div>
                )}

                {o.status === 'dismissed' && o.status_reason && (
                    <div className="rounded-lg border px-4 py-3 text-sm">
                        <span className="font-medium">{t('Dismissed:')}</span>{' '}
                        {o.status_reason}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="grid content-start gap-4 rounded-xl border p-5">
                        <div className="flex items-center justify-between">
                            <IdBadge>{o.number}</IdBadge>
                            <StatusBadge status={o.status} />
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
                                    {t('Description')}
                                </h2>
                                <p className="mt-1 whitespace-pre-line">
                                    {o.description}
                                </p>
                            </div>
                            {o.immediate_action && (
                                <div>
                                    <h2 className="text-sm text-muted-foreground">
                                        {t('Immediate action taken')}
                                    </h2>
                                    <p className="mt-1 whitespace-pre-line">
                                        {o.immediate_action}
                                    </p>
                                </div>
                            )}
                            {o.photos.length > 0 && (
                                <PhotoGallery
                                    photos={o.photos}
                                    url={(id) => photoRoutes.show.url(id)}
                                />
                            )}
                        </section>

                        <ActionsPanel
                            actions={o.actions}
                            owners={owners}
                            sourceNumber={o.number}
                            store={observationRoutes.actions.store(o.id)}
                            open={live}
                            emptyText="No actions yet. Close the observation if nothing more is needed."
                        />
                    </div>
                </div>
            </div>

            <FormDialog
                open={step === 'review'}
                onOpenChange={(open) => !open && setStep(null)}
                title="Review observation"
                description="What it is about and the crew involved, for the trends on the dashboard."
                icon={ClipboardPen}
                onSubmit={(e) => {
                    e.preventDefault();
                    review.put(observationRoutes.review.url(o.id), finish);
                }}
                processing={review.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="obs-review-topic">{t('Topic')}</Label>
                        <SelectField
                            id="obs-review-topic"
                            value={review.data.topic}
                            onChange={(e) =>
                                review.setData('topic', e.target.value)
                            }
                        >
                            <option value="">{t('Not set')}</option>
                            {OBSERVATION_TOPICS.map((topic) => (
                                <option key={topic.value} value={topic.value}>
                                    {t(topic.label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={review.errors.topic} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="obs-review-crew">{t('Crew')}</Label>
                        <SelectField
                            id="obs-review-crew"
                            value={review.data.contractor_id}
                            onChange={(e) =>
                                review.setData('contractor_id', e.target.value)
                            }
                        >
                            <option value="">
                                {t('Own staff / not known')}
                            </option>
                            {contractors.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={review.errors.contractor_id} />
                    </div>
                </div>
            </FormDialog>

            <FormDialog
                open={step === 'dismiss'}
                onOpenChange={(open) => !open && setStep(null)}
                title="Dismiss observation"
                description="For a duplicate or a report that is not a hazard. It is kept but left out of the counts."
                icon={Ban}
                submitLabel="Dismiss"
                onSubmit={(e) => {
                    e.preventDefault();
                    dismissal.put(observationRoutes.dismiss.url(o.id), finish);
                }}
                processing={dismissal.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="obs-dismiss-reason">
                        {t('Reason')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Textarea
                        id="obs-dismiss-reason"
                        rows={3}
                        placeholder={t('e.g. Same as OBS-2026-0012')}
                        value={dismissal.data.reason}
                        onChange={(e) =>
                            dismissal.setData('reason', e.target.value)
                        }
                    />
                    <InputError message={dismissal.errors.reason} />
                </div>
            </FormDialog>

            <FormDialog
                open={step === 'recognise'}
                onOpenChange={(open) => !open && setStep(null)}
                title="Observation of the month"
                description="One per site each month. It shows on everyone's dashboard with the reporter's name."
                icon={Trophy}
                submitLabel="Choose"
                onSubmit={(e) => {
                    e.preventDefault();
                    recognition.put(observationRoutes.recognise.url(o.id), {
                        ...finish,
                        onError: (e) => e.status && setStep(null),
                    });
                }}
                processing={recognition.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="obs-recognise-note">
                        {t('Why it stood out')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Textarea
                        id="obs-recognise-note"
                        rows={3}
                        value={recognition.data.note}
                        onChange={(e) =>
                            recognition.setData('note', e.target.value)
                        }
                    />
                    <InputError message={recognition.errors.note} />
                </div>
            </FormDialog>

            <ConfirmDialog
                open={closing}
                onOpenChange={setClosing}
                title="Close observation"
                description="Close this observation? It can no longer take new actions."
                confirmLabel="Close"
                tone="default"
                onConfirm={() =>
                    router.put(
                        observationRoutes.close(o.id),
                        {},
                        {
                            preserveScroll: true,
                            onSuccess: () => setClosing(false),
                        },
                    )
                }
            />

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                description="This observation and its photos will be permanently deleted."
                onConfirm={() => router.delete(observationRoutes.destroy(o.id))}
            />
        </>
    );
}

ShowObservation.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Observations', href: observationRoutes.index() },
    ],
};
