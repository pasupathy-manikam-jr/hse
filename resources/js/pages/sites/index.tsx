import { Head, router, useForm } from '@inertiajs/react';
import { MapPinned, Plus, SquarePen, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { IdBadge } from '@/components/table-cells';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import siteRoutes from '@/routes/sites';
import type { Paginated, TableFilters } from '@/types';

type Site = {
    id: number;
    code: string;
    name: string;
    address: string | null;
    active: boolean;
    users_count: number;
    areas: { id: number; name: string }[];
};

const blank = { code: '', name: '', address: '', active: true };

export default function Sites({
    sites,
    filters,
}: {
    sites: Paginated<Site>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<Site | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Site | null>(null);
    const [areasOf, setAreasOf] = useState<number | null>(null);
    const form = useForm(blank);
    const areaForm = useForm({ name: '' });
    // Read from props so the list refreshes after each add/remove.
    const areaSite = sites.data.find((s) => s.id === areasOf) ?? null;

    const openForm = (site: Site | null) => {
        setEditing(site);
        form.clearErrors();
        form.setData(
            site
                ? {
                      code: site.code,
                      name: site.name,
                      address: site.address ?? '',
                      active: site.active,
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Site>[] = [
        {
            key: 'code',
            label: 'Code',
            sortable: true,
            render: (s) => <IdBadge>{s.code}</IdBadge>,
        },
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (s) => (
                <div>
                    <div className="font-medium">{s.name}</div>
                    <div className="text-muted-foreground">{s.address}</div>
                </div>
            ),
        },
        {
            key: 'areas',
            label: 'Areas',
            render: (s) => (
                <div className="flex max-w-md flex-wrap gap-1">
                    {s.areas.map((a) => (
                        <Badge key={a.id} variant="outline">
                            {a.name}
                        </Badge>
                    ))}
                </div>
            ),
        },
        { key: 'users', label: 'Users', render: (s) => s.users_count },
        {
            key: 'active',
            label: 'Status',
            render: (s) => (
                <StatusBadge status={s.active ? 'active' : 'inactive'} />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Sites')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Sites"
                    description="Workplaces and the areas inside them. Every HSE record belongs to a site."
                    action={
                        can('create-sites') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Site')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={sites}
                    columns={columns}
                    filters={filters}
                    url={siteRoutes.index()}
                    actions={(site) => (
                        <>
                            {can('edit-sites') && (
                                <>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Areas')}
                                        onClick={() => {
                                            areaForm.reset();
                                            areaForm.clearErrors();
                                            setAreasOf(site.id);
                                        }}
                                    >
                                        <MapPinned />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={t('Edit')}
                                        onClick={() => openForm(site)}
                                    >
                                        <SquarePen />
                                    </Button>
                                </>
                            )}
                            {can('delete-sites') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(site)}
                                >
                                    <Trash2 />
                                </Button>
                            )}
                        </>
                    )}
                />
            </div>

            <FormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                title={editing ? 'Edit Site' : 'Add Site'}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? siteRoutes.update(editing.id)
                            : siteRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="site-code">
                            {t('Code')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="site-code"
                            required
                            maxLength={20}
                            value={form.data.code}
                            onChange={(e) =>
                                form.setData(
                                    'code',
                                    e.target.value.toUpperCase(),
                                )
                            }
                        />
                        <InputError message={form.errors.code} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="site-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="site-name"
                            required
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2 sm:col-span-3">
                        <Label htmlFor="site-address">{t('Address')}</Label>
                        <Input
                            id="site-address"
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                        />
                        <InputError message={form.errors.address} />
                    </div>
                    <Label className="flex items-center gap-2 sm:col-span-3">
                        <Checkbox
                            checked={form.data.active}
                            onCheckedChange={(checked) =>
                                form.setData('active', checked === true)
                            }
                        />
                        {t('Active')}
                    </Label>
                </div>
            </FormDialog>

            <Dialog
                open={areaSite !== null}
                onOpenChange={(open) => !open && setAreasOf(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Areas of :site', {
                                site: areaSite?.name ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'Zones where incidents, observations and permits are located.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    {areaSite && (
                        <>
                            <ul className="divide-y rounded-md border">
                                {areaSite.areas.length === 0 && (
                                    <li className="p-3 text-sm text-muted-foreground">
                                        {t('No areas yet.')}
                                    </li>
                                )}
                                {areaSite.areas.map((area) => (
                                    <li
                                        key={area.id}
                                        className="flex items-center justify-between px-3 py-1.5 text-sm"
                                    >
                                        {area.name}
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t('Remove :area', {
                                                area: area.name,
                                            })}
                                            onClick={() =>
                                                router.delete(
                                                    siteRoutes.areas.destroy([
                                                        areaSite.id,
                                                        area.id,
                                                    ]),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <X />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                            <form
                                className="grid gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    areaForm.submit(
                                        siteRoutes.areas.store(areaSite.id),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => areaForm.reset(),
                                        },
                                    );
                                }}
                            >
                                <Label htmlFor="area-name">
                                    {t('New area')}
                                </Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="area-name"
                                        required
                                        value={areaForm.data.name}
                                        onChange={(e) =>
                                            areaForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        disabled={areaForm.processing}
                                    >
                                        <Plus /> {t('Add')}
                                    </Button>
                                </div>
                                <InputError message={areaForm.errors.name} />
                            </form>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This site and its areas will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(siteRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Sites.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sites', href: siteRoutes.index() },
    ],
};
