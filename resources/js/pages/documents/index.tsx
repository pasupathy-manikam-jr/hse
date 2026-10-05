import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpenCheck, Eye, FilePlus, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { DocumentFields } from '@/components/document-fields';
import type { DocumentFormData } from '@/components/document-fields';
import { FormDialog } from '@/components/form-dialog';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { DOCUMENT_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import documentRoutes from '@/routes/documents';
import type { Paginated, TableFilters } from '@/types';

type Row = {
    id: number;
    number: string;
    title: string;
    type: string;
    next_review_on: string | null;
    owner: { id: number; name: string } | null;
    effective_revision: { id: number; revision: string } | null;
};

const dueSoon = (date: string | null) =>
    date !== null &&
    new Date(`${date}T00:00:00`).getTime() - Date.now() < 30 * 86_400_000;

export default function Documents({
    documents,
    people,
    clauses,
    filters,
}: {
    documents: Paginated<Row>;
    people: { id: number; name: string }[];
    clauses: { id: number; number: string; title: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [creating, setCreating] = useState(false);
    const form = useForm<DocumentFormData>({
        number: '',
        title: '',
        type: 'procedure',
        owner_id: '',
        review_interval_months: '12',
        clause_ids: [],
    });
    const url = documentRoutes.index();

    const columns: Column<Row>[] = [
        {
            key: 'number',
            label: 'No.',
            sortable: true,
            render: (d) => <IdBadge>{d.number}</IdBadge>,
        },
        {
            key: 'title',
            label: 'Document',
            sortable: true,
            className: 'min-w-64',
            render: (d) => (
                <div>
                    <div className="font-medium">{d.title}</div>
                    <div className="text-muted-foreground">
                        {t(labelOf(DOCUMENT_TYPES, d.type))}
                        {d.owner && ` · ${d.owner.name}`}
                    </div>
                </div>
            ),
        },
        {
            key: 'revision',
            label: 'In force',
            render: (d) =>
                d.effective_revision ? (
                    <StatusBadge
                        status="effective"
                        label={t('Rev :r', {
                            r: d.effective_revision.revision,
                        })}
                    />
                ) : (
                    <StatusBadge status="draft" label={t('Not yet issued')} />
                ),
        },
        {
            key: 'next_review_on',
            label: 'Next review',
            sortable: true,
            render: (d) => (
                <DateCell value={d.next_review_on}>
                    {dueSoon(d.next_review_on) && (
                        <StatusBadge status="pending" label={t('Due')} />
                    )}
                </DateCell>
            ),
        },
    ];

    return (
        <>
            <Head title={t('Documents')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Documents"
                    description="Controlled HSE documents: policies, safe work procedures, emergency plans. One revision is in force at a time."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={documentRoutes.reading()}>
                                    <BookOpenCheck /> {t('My reading')}
                                </Link>
                            </Button>
                            {can('create-documents') && (
                                <Button
                                    onClick={() => {
                                        form.clearErrors();
                                        setCreating(true);
                                    }}
                                >
                                    <Plus /> {t('New document')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={documents}
                    columns={columns}
                    filters={filters}
                    url={url}
                    toolbar={
                        <>
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="type"
                                label="All types"
                                options={DOCUMENT_TYPES.map((d) => ({
                                    id: d.value,
                                    name: t(d.label),
                                }))}
                            />
                            <FilterSelect
                                url={url}
                                filters={filters}
                                name="review"
                                label="Any review state"
                                options={[{ id: 'due', name: t('Review due') }]}
                            />
                        </>
                    }
                    actions={(d) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={documentRoutes.show(d.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>

            <FormDialog
                open={creating}
                onOpenChange={setCreating}
                title="New document"
                description="A draft revision A is started; upload the file on the next page."
                icon={FilePlus}
                submitLabel="Create"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(documentRoutes.store.url());
                }}
                processing={form.processing}
            >
                <DocumentFields form={form} people={people} clauses={clauses} />
            </FormDialog>
        </>
    );
}

Documents.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents', href: documentRoutes.index() },
    ],
};
