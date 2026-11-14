import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, HeartPulse, Plus } from 'lucide-react';
import { useState } from 'react';
import { DateCell } from '@/components/table-cells';
import { FormDialog } from '@/components/form-dialog';
import {
    HealthProgrammeFields,
    blankProgramme,
} from '@/components/health-programme-fields';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { HEALTH_HAZARDS, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import healthRoutes from '@/routes/health';

type Programme = {
    id: number;
    name: string;
    hazard: string;
    schedule_ii: boolean;
    active: boolean;
    exam_interval_months: number;
    members_count: number;
    due_count: number;
    overdue_count: number;
    monitoring_due_on: string | null;
    site: { id: number; code: string };
    chemical: { id: number; name: string } | null;
};

export default function HealthProgrammes({
    programmes,
    sites,
    chemicals,
}: {
    programmes: Programme[];
    sites: { id: number; code: string; name: string }[];
    chemicals: { id: number; site_id: number; name: string }[];
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [creating, setCreating] = useState(false);
    const form = useForm({
        ...blankProgramme,
        site_id: String(sites.length === 1 ? sites[0].id : ''),
    });

    return (
        <>
            <Head title={t('Health surveillance')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Health surveillance"
                    description="Workers exposed to noise or hazardous chemicals, their examinations and the exposure monitoring (Noise Exposure Regulations 2019, USECHH Regulations 2000). Medical results are visible to HSE managers only."
                    action={
                        can('edit-health') && (
                            <Button
                                onClick={() => {
                                    form.clearErrors();
                                    setCreating(true);
                                }}
                            >
                                <Plus /> {t('New programme')}
                            </Button>
                        )
                    }
                />

                {programmes.length === 0 ? (
                    <p className="rounded-xl border p-6 text-sm text-muted-foreground">
                        {t(
                            'No programmes yet. Start one for each exposure that needs surveillance, such as noise above 85 dB(A) or a Schedule II chemical.',
                        )}
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-muted-foreground">
                                <tr className="[&>th]:px-3 [&>th]:py-2 [&>th]:font-medium">
                                    <th>{t('Programme')}</th>
                                    <th>{t('Workers')}</th>
                                    <th>{t('Examinations')}</th>
                                    <th>{t('Monitoring due')}</th>
                                    <th className="sr-only">{t('Open')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {programmes.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="[&>td]:px-3 [&>td]:py-2"
                                    >
                                        <td>
                                            <div className="font-medium">
                                                {p.name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {p.site.code} ·{' '}
                                                {t(
                                                    labelOf(
                                                        HEALTH_HAZARDS,
                                                        p.hazard,
                                                    ),
                                                )}
                                                {p.chemical &&
                                                    ` · ${p.chemical.name}`}
                                                {p.schedule_ii &&
                                                    ` · ${t('Schedule II')}`}
                                                {!p.active &&
                                                    ` · ${t('inactive')}`}
                                            </div>
                                        </td>
                                        <td className="tabular-nums">
                                            {p.members_count}
                                        </td>
                                        <td>
                                            {p.overdue_count > 0 ? (
                                                <StatusBadge
                                                    status="overdue"
                                                    label={t(':n overdue', {
                                                        n: p.overdue_count,
                                                    })}
                                                />
                                            ) : p.due_count > 0 ? (
                                                <StatusBadge
                                                    status="due-soon"
                                                    label={t(':n due soon', {
                                                        n: p.due_count,
                                                    })}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {t('Up to date')}
                                                </span>
                                            )}
                                        </td>
                                        <td>
                                            {p.monitoring_due_on ? (
                                                <DateCell
                                                    value={p.monitoring_due_on}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="text-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Open')}
                                                asChild
                                            >
                                                <Link
                                                    href={healthRoutes.show(
                                                        p.id,
                                                    )}
                                                >
                                                    <Eye />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <FormDialog
                open={creating}
                onOpenChange={setCreating}
                title="New health programme"
                description="One per exposure at a site. Add the exposed workers on the next page."
                icon={HeartPulse}
                submitLabel="Create"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(healthRoutes.store.url());
                }}
                processing={form.processing}
            >
                <HealthProgrammeFields
                    form={form}
                    sites={sites}
                    chemicals={chemicals}
                />
            </FormDialog>
        </>
    );
}

HealthProgrammes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Health surveillance', href: healthRoutes.index() },
    ],
};
