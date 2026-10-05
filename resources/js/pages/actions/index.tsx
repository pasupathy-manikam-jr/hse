import { Head, Link } from '@inertiajs/react';
import { ActionControls } from '@/components/action-controls';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { ClampedText, DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect, StatusTabs } from '@/components/table-filters';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { CONTROL_LEVELS, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import actionRoutes from '@/routes/actions';
import type { Action, Paginated, TableFilters } from '@/types';

type Row = Action & {
    source_ref: { number: string | null; url: string | null };
};

const isOverdue = (a: Action) =>
    a.status === 'open' && new Date(`${a.due_on}T23:59:59`) < new Date();

export default function Actions({
    actions,
    counts,
    filters,
}: {
    actions: Paginated<Row>;
    counts: Record<string, number>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const url = actionRoutes.index();

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (a) => <IdBadge>{a.number}</IdBadge>,
        },
        {
            key: 'description',
            label: 'Action',
            className: 'min-w-64',
            render: (a) => (
                <div className="grid gap-1">
                    <ClampedText text={a.description} />
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <StatusBadge
                            status={a.priority}
                            label={t(':p priority', { p: a.priority })}
                        />
                        {t(labelOf(CONTROL_LEVELS, a.control_level))}
                    </div>
                </div>
            ),
        },
        {
            key: 'source',
            label: 'From',
            render: (a) =>
                a.source_ref.url ? (
                    <Link
                        href={a.source_ref.url}
                        className="whitespace-nowrap text-blue-600 hover:underline"
                    >
                        {a.source_ref.number}
                    </Link>
                ) : (
                    <span className="whitespace-nowrap">
                        {a.source_ref.number}
                    </span>
                ),
        },
        {
            key: 'due_on',
            label: 'Owner · due',
            sortable: true,
            render: (a) => (
                <div className="grid gap-1">
                    <span className="whitespace-nowrap">{a.owner.name}</span>
                    <DateCell value={a.due_on}>
                        {isOverdue(a) && <StatusBadge status="overdue" />}
                    </DateCell>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (a) => <StatusBadge status={a.status} />,
        },
    ];

    return (
        <>
            <Head title={t('Actions')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={can('manage-actions') ? 'Actions' : 'My actions'}
                    description="Corrective actions from every source. The owner marks an action done; someone else verifies it."
                />

                <DataTable
                    data={actions}
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
                        can('manage-actions') && (
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="owner"
                                label="Everyone's actions"
                                options={[
                                    { id: 'me', name: t('Mine') },
                                    { id: 'overdue', name: t('Overdue') },
                                ]}
                            />
                        )
                    }
                    actions={(a) => (
                        <div className="flex justify-end gap-2">
                            <ActionControls action={a} compact />
                        </div>
                    )}
                />
            </div>
        </>
    );
}

Actions.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Actions', href: actionRoutes.index() },
    ],
};
