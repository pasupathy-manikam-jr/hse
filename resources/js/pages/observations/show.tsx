import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Lock, MapPin, Siren, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ActionsPanel } from '@/components/actions-panel';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { PhotoGallery } from '@/components/photo-picker';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { OBSERVATION_TYPES, labelOf } from '@/lib/hse';
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

export default function ShowObservation({
    observation: o,
    owners,
}: {
    observation: Detail;
    owners: { id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const [closing, setClosing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const openActions = o.actions.filter((a) => a.status !== 'verified');

    const facts: [string, ReactNode][] = [
        ['Type', t(labelOf(OBSERVATION_TYPES, o.type))],
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
            'Closed',
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
                            {can('edit-observations') &&
                                o.status !== 'closed' && (
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
                            open={o.status !== 'closed'}
                            emptyText="No actions yet. Close the observation if nothing more is needed."
                        />
                    </div>
                </div>
            </div>

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
