import { Head, Link } from '@inertiajs/react';
import { BookOpenText, Eye, Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { CLASSIFICATIONS, INCIDENT_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import incidentRoutes from '@/routes/incidents';
import type { Incident, Paginated, TableFilters } from '@/types';

type Row = Incident & { people_count: number };

export default function Incidents({
    incidents,
    counts,
    sites,
    filters,
}: {
    incidents: Paginated<Row>;
    counts: Record<string, number>;
    sites: { id: number; name: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const url = incidentRoutes.index();

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (i) => <IdBadge>{i.number}</IdBadge>,
        },
        {
            key: 'title',
            label: 'Incident',
            className: 'min-w-64',
            render: (i) => (
                <div>
                    <div className="font-medium">{i.title}</div>
                    <div className="text-muted-foreground">
                        {t(labelOf(INCIDENT_TYPES, i.type))} · {i.site.code}
                        {i.area && ` · ${i.area.name}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'classification',
            label: 'Classification',
            render: (i) => (
                <div className="flex flex-col items-start gap-1">
                    <StatusBadge
                        status={i.classification}
                        label={t(labelOf(CLASSIFICATIONS, i.classification))}
                    />
                    {i.recordable && (
                        <span className="text-xs font-medium text-red-700 dark:text-red-400">
                            {t('Recordable')}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'occurred_at',
            label: 'Occurred',
            sortable: true,
            render: (i) => (
                <div className="whitespace-nowrap">
                    <div>{dateTime(i.occurred_at)}</div>
                    <div className="text-muted-foreground">
                        {t(i.people_count === 1 ? '1 person' : ':n people', {
                            n: i.people_count,
                        })}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (i) => <StatusBadge status={i.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Incidents')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Incidents"
                    description="Injuries, illnesses, damage, releases and fires: who was involved, why it happened and what was done."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={incidentRoutes.log()}>
                                    <BookOpenText /> {t('Injury log')}
                                </Link>
                            </Button>
                            {can('create-incidents') && (
                                <Button asChild>
                                    <Link href={incidentRoutes.create()}>
                                        <Plus /> {t('Report incident')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={incidents}
                    columns={columns}
                    filters={filters}
                    url={url}
                    tabs={
                        <StatusTabs
                            url={url}
                            filters={filters}
                            counts={counts}
                        />
                    }
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="type"
                                label="All types"
                                options={INCIDENT_TYPES.map((o) => ({
                                    id: o.value,
                                    name: t(o.label),
                                }))}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="classification"
                                label="Any classification"
                                options={CLASSIFICATIONS.map((c) => ({
                                    id: c.value,
                                    name: t(c.label),
                                }))}
                            />
                            {sites.length > 1 && (
                                <FilterSelect
                                    url={url}
                                    filters={filters}
                                    name="site_id"
                                    label="All sites"
                                    options={sites}
                                />
                            )}
                        </>
                    }
                    actions={(i) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={incidentRoutes.show(i.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>
        </>
    );
}

Incidents.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Incidents', href: incidentRoutes.index() },
    ],
};
