import { Head, Link } from '@inertiajs/react';
import { Camera, Eye, Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ClampedText, IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { OBSERVATION_TYPES, POTENTIALS, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import observationRoutes from '@/routes/observations';
import type { Observation, Paginated, TableFilters } from '@/types';

type Row = Observation & { photos_count: number };

export default function Observations({
    observations,
    counts,
    sites,
    filters,
}: {
    observations: Paginated<Row>;
    counts: Record<string, number>;
    sites: { id: number; name: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const url = observationRoutes.index();

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (o) => <IdBadge>{o.number}</IdBadge>,
        },
        {
            key: 'type',
            label: 'Type',
            render: (o) => (
                <div className="whitespace-nowrap">
                    <div className="flex items-center gap-2 font-medium">
                        {t(labelOf(OBSERVATION_TYPES, o.type))}
                        <StatusBadge status={o.potential} />
                    </div>
                    <div className="text-muted-foreground">
                        {o.site.code}
                        {o.area && ` · ${o.area.name}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'description',
            label: 'Description',
            className: 'min-w-64',
            render: (o) => (
                <div className="flex items-start gap-2">
                    <ClampedText text={o.description} />
                    {o.photos_count > 0 && (
                        <span className="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                            <Camera className="size-3.5" />
                            {o.photos_count}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'observed_at',
            label: 'Observed',
            sortable: true,
            render: (o) => (
                <div className="whitespace-nowrap">
                    <div>{dateTime(o.observed_at)}</div>
                    <div className="text-muted-foreground">
                        {o.reporter?.name ?? (
                            <span className="italic">{t('Anonymous')}</span>
                        )}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (o) => <StatusBadge status={o.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Observations')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Observations"
                    description="Unsafe acts and conditions, near misses, environmental issues and good practice reported from the field."
                    action={
                        can('create-observations') && (
                            <Button asChild>
                                <Link href={observationRoutes.create()}>
                                    <Plus /> {t('Report')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={observations}
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
                                options={OBSERVATION_TYPES.map((o) => ({
                                    id: o.value,
                                    name: t(o.label),
                                }))}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="potential"
                                label="Any potential"
                                options={POTENTIALS.map((p) => ({
                                    id: p.value,
                                    name: t(p.label),
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
                    actions={(o) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={observationRoutes.show(o.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>
        </>
    );
}

Observations.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Observations', href: observationRoutes.index() },
    ],
};
