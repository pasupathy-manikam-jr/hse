import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardList, Eye, Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import checklistRoutes from '@/routes/checklists';
import inspectionRoutes from '@/routes/inspections';
import type { Paginated, TableFilters } from '@/types';

type Template = {
    id: number;
    name: string;
    description: string | null;
    active: boolean;
    items_count: number;
};

export default function Checklists({
    templates,
    filters,
}: {
    templates: Paginated<Template>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [creating, setCreating] = useState(false);
    const form = useForm({ name: '', description: '', active: true });

    const columns: Column<Template>[] = [
        {
            key: 'name',
            label: 'Checklist',
            sortable: true,
            render: (c) => (
                <div>
                    <div className="font-medium">{c.name}</div>
                    <div className="text-muted-foreground">{c.description}</div>
                </div>
            ),
        },
        {
            key: 'items',
            label: 'Questions',
            render: (c) => c.items_count,
        },
        {
            key: 'active',
            label: 'Status',
            render: (c) => (
                <StatusBadge status={c.active ? 'active' : 'inactive'} />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Checklists')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Checklists"
                    description="Inspection templates. Changing one never alters inspections already done with it."
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={inspectionRoutes.index()}>
                                    {t('Inspections')}
                                </Link>
                            </Button>
                            {can('create-checklists') && (
                                <Button
                                    onClick={() => {
                                        form.reset();
                                        form.clearErrors();
                                        setCreating(true);
                                    }}
                                >
                                    <Plus /> {t('New checklist')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <DataTable
                    data={templates}
                    columns={columns}
                    filters={filters}
                    url={checklistRoutes.index()}
                    actions={(c) => (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('View')}
                            asChild
                        >
                            <Link href={checklistRoutes.show(c.id)}>
                                <Eye />
                            </Link>
                        </Button>
                    )}
                />
            </div>

            <FormDialog
                open={creating}
                onOpenChange={setCreating}
                title="New checklist"
                description="Name it, then add its questions on the next page."
                icon={ClipboardList}
                submitLabel="Create"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(checklistRoutes.store.url());
                }}
                processing={form.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="cl-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="cl-name"
                            placeholder={t('e.g. Daily site walk')}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cl-description">
                            {t('Description')}
                        </Label>
                        <Textarea
                            id="cl-description"
                            rows={2}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                    </div>
                </div>
            </FormDialog>
        </>
    );
}

Checklists.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Checklists', href: checklistRoutes.index() },
    ],
};
