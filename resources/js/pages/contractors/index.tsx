import { Head, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    BadgeX,
    HardHat,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DatePicker } from '@/components/date-picker';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { DateCell } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import contractorRoutes from '@/routes/contractors';
import type { Paginated, TableFilters } from '@/types';

type Contractor = {
    id: number;
    name: string;
    registration_no: string | null;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    insurance_expires_on: string | null;
    approved: boolean;
    workers_count: number;
};

const FIELDS = [
    ['name', 'Company name', 'text'],
    ['registration_no', 'Registration no.', 'text'],
    ['contact_name', 'Safety contact', 'text'],
    ['email', 'Email', 'email'],
    ['phone', 'Phone', 'tel'],
    ['insurance_expires_on', 'Insurance expires', 'date'],
] as const;

const blank = {
    name: '',
    registration_no: '',
    contact_name: '',
    email: '',
    phone: '',
    insurance_expires_on: '',
};

// Insurance is valid through the whole of its expiry day (same rule as Contractor::canWork()).
const insuranceExpired = (date: string | null) =>
    date !== null && new Date(`${date}T23:59:59`) < new Date();

export default function Contractors({
    contractors,
    filters,
}: {
    contractors: Paginated<Contractor>;
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<Contractor | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<Contractor | null>(null);
    const form = useForm(blank);

    const openForm = (contractor: Contractor | null) => {
        setEditing(contractor);
        form.clearErrors();
        form.setData(
            contractor
                ? (Object.fromEntries(
                      Object.keys(blank).map((key) => [
                          key,
                          contractor[key as keyof typeof blank] ?? '',
                      ]),
                  ) as typeof blank)
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<Contractor>[] = [
        {
            key: 'name',
            label: 'Company',
            sortable: true,
            render: (c) => (
                <div>
                    <div className="font-medium">{c.name}</div>
                    <div className="text-muted-foreground">
                        {c.registration_no}
                    </div>
                </div>
            ),
        },
        {
            key: 'contact',
            label: 'Safety contact',
            render: (c) => (
                <div>
                    <div>{c.contact_name}</div>
                    <div className="text-muted-foreground">
                        {c.email ?? c.phone}
                    </div>
                </div>
            ),
        },
        { key: 'workers', label: 'Workers', render: (c) => c.workers_count },
        {
            key: 'insurance_expires_on',
            label: 'Insurance expires',
            sortable: true,
            render: (c) => (
                <DateCell value={c.insurance_expires_on}>
                    {insuranceExpired(c.insurance_expires_on) && (
                        <StatusBadge status="expired" />
                    )}
                </DateCell>
            ),
        },
        {
            key: 'approved',
            label: 'Status',
            render: (c) => (
                <StatusBadge status={c.approved ? 'approved' : 'pending'} />
            ),
        },
    ];

    return (
        <>
            <Head title={t('Contractors')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Contractors"
                    description="Contractor companies. Only approved contractors with current insurance can be issued permits."
                    action={
                        can('create-contractors') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add Contractor')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={contractors}
                    columns={columns}
                    filters={filters}
                    url={contractorRoutes.index()}
                    toolbar={
                        <FilterSelect
                            url={contractorRoutes.index()}
                            filters={filters}
                            name="approved"
                            label="All statuses"
                            options={[
                                { id: '1', name: t('Approved') },
                                { id: '0', name: t('Pending') },
                            ]}
                        />
                    }
                    actions={(contractor) => (
                        <>
                            {can('approve-contractors') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t(
                                        contractor.approved
                                            ? 'Withdraw approval'
                                            : 'Approve',
                                    )}
                                    onClick={() =>
                                        router.put(
                                            contractorRoutes.approval(
                                                contractor.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {contractor.approved ? (
                                        <BadgeX />
                                    ) : (
                                        <BadgeCheck />
                                    )}
                                </Button>
                            )}
                            {can('edit-contractors') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(contractor)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-contractors') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(contractor)}
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
                title={editing ? 'Edit Contractor' : 'Add Contractor'}
                description="The company and its safety contact. Approval is a separate step."
                icon={HardHat}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? contractorRoutes.update(editing.id)
                            : contractorRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {FIELDS.map(([key, label, type]) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={`contractor-${key}`}>
                                {t(label)}
                                {key === 'name' && (
                                    <span className="text-destructive">*</span>
                                )}
                            </Label>
                            {type === 'date' ? (
                                <DatePicker
                                    id={`contractor-${key}`}
                                    value={form.data[key]}
                                    onChange={(value) =>
                                        form.setData(key, value)
                                    }
                                />
                            ) : (
                                <Input
                                    id={`contractor-${key}`}
                                    type={type}
                                    value={form.data[key]}
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                />
                            )}
                            <InputError message={form.errors[key]} />
                        </div>
                    ))}
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This contractor will be permanently deleted."
                onConfirm={() =>
                    deleting &&
                    router.delete(contractorRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Contractors.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Contractors', href: contractorRoutes.index() },
    ],
};
