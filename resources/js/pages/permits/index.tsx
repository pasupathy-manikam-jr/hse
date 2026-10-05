import { Head, Link } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';
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
import { PERMIT_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import permitRoutes from '@/routes/permits';
import type { Paginated, Permit, TableFilters } from '@/types';

type Row = Permit & { workers_count: number };

export default function Permits({
    permits,
    counts,
    sites,
    filters,
}: {
    permits: Paginated<Row>;
    counts: Record<string, number>;
    sites: { id: number; name: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const { dateTime } = useFormat();
    const can = useCan();
    const url = permitRoutes.index();

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (p) => <IdBadge>{p.number}</IdBadge>,
        },
        {
            key: 'description',
            label: 'Work',
            className: 'min-w-64',
            render: (p) => (
                <div>
                    <div className="font-medium">
                        {t(labelOf(PERMIT_TYPES, p.type))}
                    </div>
                    <div className="line-clamp-1 text-muted-foreground">
                        {p.site.code}
                        {p.area && ` · ${p.area.name}`} · {p.description}
                    </div>
                </div>
            ),
        },
        {
            key: 'valid_from',
            label: 'Valid',
            sortable: true,
            render: (p) => (
                <div className="text-sm whitespace-nowrap">
                    <div>{dateTime(p.valid_from)}</div>
                    <div className="text-muted-foreground">
                        {t('to')} {dateTime(p.valid_to)}
                    </div>
                </div>
            ),
        },
        {
            key: 'workers',
            label: 'Crew',
            render: (p) => (
                <div className="text-sm">
                    <div>{t(':n workers', { n: p.workers_count })}</div>
                    <div className="text-muted-foreground">
                        {p.contractor?.name ?? t('Own staff')}
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (p) => <StatusBadge status={p.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Permits to work')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Permits to work"
                    description="Control of high-risk work: precautions, competencies, gas tests and isolations before anyone starts."
                    action={
                        can('create-permits') && (
                            <Button asChild>
                                <Link href={permitRoutes.create()}>
                                    <Plus /> {t('Request permit')}
                                </Link>
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={permits}
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
                                options={PERMIT_TYPES.map((p) => ({
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
                    actions={(p) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={permitRoutes.show(p.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>
        </>
    );
}

Permits.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Permits to work', href: permitRoutes.index() },
    ],
};
