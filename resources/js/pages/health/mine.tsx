import { Head } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { useTranslation } from '@/hooks/use-translation';
import {
    EXAM_TYPES,
    HEALTH_HAZARDS,
    HEALTH_OUTCOMES,
    labelOf,
} from '@/lib/hse';
import { dashboard } from '@/routes';
import healthRoutes from '@/routes/health';

type Programme = {
    id: number;
    name: string;
    hazard: string;
    site: { id: number; code: string };
};

export default function MyHealthRecords({
    memberships,
    exams,
}: {
    memberships: {
        id: number;
        started_on: string;
        ended_on: string | null;
        programme: Programme;
    }[];
    exams: {
        id: number;
        type: string;
        examined_on: string;
        provider: string;
        outcome: string;
        notes: string | null;
        file_name: string | null;
        programme: { id: number; name: string; hazard: string };
    }[];
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('My health records')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="My health records"
                    description="Your health surveillance: the exposures you are monitored for, and your results. Only you and the HSE managers can see these."
                />

                <section className="grid gap-3 rounded-xl border p-5">
                    <h2 className="font-medium">{t('Monitored for')}</h2>
                    {memberships.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'You are not in any health surveillance programme.',
                            )}
                        </p>
                    ) : (
                        <ul className="grid gap-2 text-sm">
                            {memberships.map((m) => (
                                <li key={m.id}>
                                    <span className="font-medium">
                                        {m.programme.name}
                                    </span>{' '}
                                    <span className="text-muted-foreground">
                                        · {m.programme.site.code} ·{' '}
                                        {t(
                                            labelOf(
                                                HEALTH_HAZARDS,
                                                m.programme.hazard,
                                            ),
                                        )}{' '}
                                        · {t('since :d', { d: m.started_on })}
                                        {m.ended_on &&
                                            ` ${t('until :d', { d: m.ended_on })}`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="grid gap-3 rounded-xl border p-5">
                    <h2 className="font-medium">{t('My results')}</h2>
                    {exams.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No results yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border text-sm">
                            {exams.map((e) => (
                                <li key={e.id} className="grid gap-1 px-3 py-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <DateCell value={e.examined_on} />
                                        <span className="font-medium">
                                            {e.programme.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {t(labelOf(EXAM_TYPES, e.type))} ·{' '}
                                            {e.provider}
                                        </span>
                                        <StatusBadge
                                            status={e.outcome}
                                            label={t(
                                                labelOf(
                                                    HEALTH_OUTCOMES,
                                                    e.outcome,
                                                ),
                                            )}
                                        />
                                        {e.file_name && (
                                            <a
                                                href={healthRoutes.exams.file.url(
                                                    e.id,
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 text-blue-600 hover:underline"
                                            >
                                                <FileText className="size-4" />
                                                {t('Report')}
                                            </a>
                                        )}
                                    </div>
                                    {e.notes && (
                                        <p className="text-muted-foreground">
                                            {e.notes}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

MyHealthRecords.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'My health records', href: healthRoutes.mine() },
    ],
};
