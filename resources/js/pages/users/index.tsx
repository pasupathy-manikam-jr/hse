import { Head, router, useForm } from '@inertiajs/react';
import { Plus, SquarePen, Trash2, UserPen, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import type { Column } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { DateCell, IdBadge } from '@/components/table-cells';
import { FilterSelect } from '@/components/table-filters';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import userRoutes from '@/routes/users';
import type { Paginated, TableFilters } from '@/types';

type Option = { value: string; label: string };
type SiteOption = { id: number; code: string; name: string };

type UserRow = {
    id: number;
    name: string;
    email: string;
    site_id: number | null;
    contractor_id: number | null;
    created_at: string;
    roles: { id: number; name: string }[];
    site: SiteOption | null;
    contractor: { id: number; name: string } | null;
};

const blank = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    site_id: '',
    contractor_id: '',
};

export default function Users({
    users,
    roles,
    sites,
    contractors,
    filters,
}: {
    users: Paginated<UserRow>;
    roles: Option[];
    sites: SiteOption[];
    contractors: { id: number; name: string }[];
    filters: TableFilters;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<UserRow | null>(null);
    const form = useForm(blank);
    const roleLabel = (name: string) =>
        roles.find((r) => r.value === name)?.label ?? name;

    const openForm = (user: UserRow | null) => {
        setEditing(user);
        form.clearErrors();
        form.setData(
            user
                ? {
                      name: user.name,
                      email: user.email,
                      password: '',
                      password_confirmation: '',
                      role: user.roles[0]?.name ?? '',
                      site_id: String(user.site_id ?? ''),
                      contractor_id: String(user.contractor_id ?? ''),
                  }
                : blank,
        );
        setFormOpen(true);
    };

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (u) => (
                <div>
                    <div className="font-medium">{u.name}</div>
                    <div className="text-muted-foreground">{u.email}</div>
                </div>
            ),
        },
        {
            key: 'role',
            label: 'Role',
            render: (u) =>
                u.roles.map((r) => (
                    <Badge key={r.id} variant="secondary">
                        {roleLabel(r.name)}
                    </Badge>
                )),
        },
        {
            key: 'site',
            label: 'Site',
            render: (u) =>
                u.site ? (
                    <IdBadge>{u.site.code}</IdBadge>
                ) : (
                    <span className="text-muted-foreground">
                        {t('All sites')}
                    </span>
                ),
        },
        {
            key: 'contractor',
            label: 'Employer',
            render: (u) => u.contractor?.name ?? t('Own staff'),
        },
        {
            key: 'created_at',
            label: 'Created At',
            sortable: true,
            render: (u) => <DateCell value={u.created_at} />,
        },
    ];

    return (
        <>
            <Head title={t('Users')} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Users"
                    description="People who can sign in, their role, home site and employer."
                    action={
                        can('create-users') && (
                            <Button onClick={() => openForm(null)}>
                                <Plus /> {t('Add User')}
                            </Button>
                        )
                    }
                />

                <DataTable
                    data={users}
                    columns={columns}
                    filters={filters}
                    url={userRoutes.index()}
                    toolbar={
                        <>
                            <FilterSelect
                                url={userRoutes.index()}
                                filters={filters}
                                name="role"
                                label="All roles"
                                options={roles.map((r) => ({
                                    id: r.value,
                                    name: r.label,
                                }))}
                            />
                            <FilterSelect
                                url={userRoutes.index()}
                                filters={filters}
                                name="site_id"
                                label="All sites"
                                options={sites}
                            />
                        </>
                    }
                    actions={(user) => (
                        <>
                            {can('edit-users') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Edit')}
                                    onClick={() => openForm(user)}
                                >
                                    <SquarePen />
                                </Button>
                            )}
                            {can('delete-users') && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('Delete')}
                                    onClick={() => setDeleting(user)}
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
                title={editing ? 'Edit User' : 'Add User'}
                description="Their role decides what they can open. A home site limits them to that site's records."
                icon={editing ? UserPen : UserPlus}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        editing
                            ? userRoutes.update(editing.id)
                            : userRoutes.store(),
                        {
                            preserveScroll: true,
                            onSuccess: () => setFormOpen(false),
                        },
                    );
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="user-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="user-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="user-email">
                            {t('Email')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="user-email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    {!editing && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="user-password">
                                    {t('Password')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="user-password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData('password', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-password-confirmation">
                                    {t('Confirm password')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="user-password-confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.password_confirmation}
                                    onChange={(e) =>
                                        form.setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        </>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="user-role">
                            {t('Role')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="user-role"
                            value={form.data.role}
                            onChange={(e) =>
                                form.setData('role', e.target.value)
                            }
                        >
                            <option value="">{t('Select role')}</option>
                            {roles.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {r.label}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.role} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="user-site">{t('Home site')}</Label>
                        <SelectField
                            id="user-site"
                            value={form.data.site_id}
                            onChange={(e) =>
                                form.setData('site_id', e.target.value)
                            }
                        >
                            <option value="">{t('All sites')}</option>
                            {sites.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.code} · {s.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.site_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="user-contractor">{t('Employer')}</Label>
                        <SelectField
                            id="user-contractor"
                            value={form.data.contractor_id}
                            onChange={(e) =>
                                form.setData('contractor_id', e.target.value)
                            }
                        >
                            <option value="">{t('Own staff')}</option>
                            {contractors.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.contractor_id} />
                    </div>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                description="This user will no longer be able to sign in. Their HSE records keep their name."
                onConfirm={() =>
                    deleting &&
                    router.delete(userRoutes.destroy(deleting.id), {
                        preserveScroll: true,
                        onSuccess: () => setDeleting(null),
                    })
                }
            />
        </>
    );
}

Users.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Users', href: userRoutes.index() },
    ],
};
