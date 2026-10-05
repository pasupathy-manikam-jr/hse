import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CircleAlert,
    ClipboardCheck,
    FileSearch,
    Play,
    Plus,
} from 'lucide-react';
import { useState } from 'react';
import { ActionsPanel } from '@/components/actions-panel';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { FINDING_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import actionRoutes from '@/routes/actions';
import auditRoutes from '@/routes/audits';
import type { Action } from '@/types';

type Person = { id: number; name: string };

type Detail = {
    id: number;
    number: string;
    title: string;
    scope: string | null;
    planned_on: string;
    status: 'planned' | 'in-progress' | 'completed';
    summary: string | null;
    site: { id: number; code: string; name: string };
    lead_auditor: Person | null;
    clauses: { id: number; number: string; title: string }[];
    findings: {
        id: number;
        type: string;
        description: string;
        clause: { id: number; number: string } | null;
        action: { id: number; number: string; status: string } | null;
    }[];
    actions: Action[];
};

export default function ShowAudit({
    audit: a,
    users,
}: {
    audit: Detail;
    users: Person[];
}) {
    const { t } = useTranslation();
    const can = useCan();
    const { errors } = usePage().props as { errors: Record<string, string> };
    const editable = can('edit-audits');
    const [adding, setAdding] = useState(false);
    const [completing, setCompleting] = useState(false);
    const finding = useForm({
        type: 'minor-nonconformity',
        iso_clause_id: '',
        description: '',
        owner_id: '',
        due_on: '',
    });
    const summary = useForm({ summary: a.summary ?? '' });
    const nonconformity = finding.data.type.endsWith('nonconformity');

    return (
        <>
            <Head title={a.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${a.number} · ${a.title}`}
                    description={`${a.site.code} · ${a.site.name}`}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={auditRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {editable && a.status === 'planned' && (
                                <Button
                                    onClick={() =>
                                        router.put(
                                            auditRoutes.start.url(a.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Play /> {t('Start audit')}
                                </Button>
                            )}
                            {editable && a.status === 'in-progress' && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() => {
                                            finding.reset();
                                            finding.clearErrors();
                                            setAdding(true);
                                        }}
                                    >
                                        <Plus /> {t('Add finding')}
                                    </Button>
                                    <Button onClick={() => setCompleting(true)}>
                                        <ClipboardCheck /> {t('Complete')}
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

                {errors.status && (
                    <div
                        role="alert"
                        className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    >
                        <CircleAlert className="size-4 shrink-0" />
                        {errors.status}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="grid content-start gap-4 rounded-xl border p-5">
                        <div className="flex items-center justify-between">
                            <IdBadge>{a.number}</IdBadge>
                            <StatusBadge status={a.status} />
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <div className="grid grid-cols-[7rem_1fr] gap-2">
                                <dt className="text-muted-foreground">
                                    {t('Planned')}
                                </dt>
                                <dd className="font-medium">
                                    <DateCell value={a.planned_on} />
                                </dd>
                            </div>
                            <div className="grid grid-cols-[7rem_1fr] gap-2">
                                <dt className="text-muted-foreground">
                                    {t('Lead auditor')}
                                </dt>
                                <dd className="font-medium">
                                    {a.lead_auditor?.name ?? '—'}
                                </dd>
                            </div>
                        </dl>
                        {a.scope && (
                            <p className="text-sm whitespace-pre-line">
                                {a.scope}
                            </p>
                        )}
                        <div className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">
                                {t('Clauses audited')}
                            </span>
                            {a.clauses.map((c) => (
                                <span key={c.id}>
                                    §{c.number} {c.title}
                                </span>
                            ))}
                        </div>
                    </section>

                    <div className="grid content-start gap-6 lg:col-span-2">
                        {a.summary && (
                            <section className="rounded-xl border p-5">
                                <h2 className="text-sm text-muted-foreground">
                                    {t('Summary')}
                                </h2>
                                <p className="mt-1 whitespace-pre-line">
                                    {a.summary}
                                </p>
                            </section>
                        )}

                        <section className="grid gap-3 rounded-xl border p-5">
                            <h2 className="font-medium">{t('Findings')}</h2>
                            {a.findings.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {a.status === 'planned'
                                        ? t(
                                              'Start the audit to record findings.',
                                          )
                                        : t('No findings yet.')}
                                </p>
                            ) : (
                                <ul className="divide-y">
                                    {a.findings.map((f) => (
                                        <li
                                            key={f.id}
                                            className="grid gap-1 py-3 text-sm first:pt-0 last:pb-0"
                                        >
                                            <span className="flex flex-wrap items-center gap-2">
                                                <StatusBadge
                                                    status={f.type}
                                                    label={t(
                                                        labelOf(
                                                            FINDING_TYPES,
                                                            f.type,
                                                        ),
                                                    )}
                                                />
                                                {f.clause && (
                                                    <span className="text-muted-foreground">
                                                        §{f.clause.number}
                                                    </span>
                                                )}
                                                {f.action && (
                                                    <Link
                                                        href={actionRoutes.index.url(
                                                            {
                                                                query: {
                                                                    search: f
                                                                        .action
                                                                        .number,
                                                                },
                                                            },
                                                        )}
                                                        className="text-blue-600 hover:underline"
                                                    >
                                                        {f.action.number}
                                                    </Link>
                                                )}
                                            </span>
                                            <p className="whitespace-pre-line">
                                                {f.description}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>

                        <ActionsPanel
                            actions={a.actions}
                            owners={users}
                            sourceNumber={a.number}
                            store={auditRoutes.actions.store(a.id)}
                            open
                            emptyText="Nonconformities raise actions here."
                        />
                    </div>
                </div>
            </div>

            <FormDialog
                open={adding}
                onOpenChange={setAdding}
                title="Add finding"
                description="A nonconformity raises a corrective action with an owner and a due date."
                icon={FileSearch}
                onSubmit={(e) => {
                    e.preventDefault();
                    finding.post(auditRoutes.findings.store.url(a.id), {
                        preserveScroll: true,
                        onSuccess: () => setAdding(false),
                    });
                }}
                processing={finding.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="fnd-type">{t('Type')}</Label>
                        <SelectField
                            id="fnd-type"
                            value={finding.data.type}
                            onChange={(e) =>
                                finding.setData('type', e.target.value)
                            }
                        >
                            {FINDING_TYPES.map((f) => (
                                <option key={f.value} value={f.value}>
                                    {t(f.label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={finding.errors.type} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="fnd-clause">{t('Clause')}</Label>
                        <SelectField
                            id="fnd-clause"
                            value={finding.data.iso_clause_id}
                            onChange={(e) =>
                                finding.setData('iso_clause_id', e.target.value)
                            }
                        >
                            <option value="">
                                {t('Not tied to a clause')}
                            </option>
                            {a.clauses.map((c) => (
                                <option key={c.id} value={c.id}>
                                    §{c.number} {c.title}
                                </option>
                            ))}
                        </SelectField>
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="fnd-description">
                            {t('Finding and evidence')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="fnd-description"
                            rows={3}
                            value={finding.data.description}
                            onChange={(e) =>
                                finding.setData('description', e.target.value)
                            }
                        />
                        <InputError message={finding.errors.description} />
                    </div>
                    {nonconformity && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="fnd-owner">
                                    {t('Action owner')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <SelectField
                                    id="fnd-owner"
                                    value={finding.data.owner_id}
                                    onChange={(e) =>
                                        finding.setData(
                                            'owner_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('Select person')}
                                    </option>
                                    {users.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name}
                                        </option>
                                    ))}
                                </SelectField>
                                <InputError message={finding.errors.owner_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fnd-due">
                                    {t('Action due')}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <DatePicker
                                    id="fnd-due"
                                    value={finding.data.due_on}
                                    onChange={(v) =>
                                        finding.setData('due_on', v)
                                    }
                                />
                                <InputError message={finding.errors.due_on} />
                            </div>
                        </>
                    )}
                </div>
            </FormDialog>

            <FormDialog
                open={completing}
                onOpenChange={setCompleting}
                title="Complete audit"
                description="Summarise the conclusions. Findings can no longer be added afterwards."
                icon={ClipboardCheck}
                submitLabel="Complete"
                onSubmit={(e) => {
                    e.preventDefault();
                    summary.put(auditRoutes.complete.url(a.id), {
                        preserveScroll: true,
                        onSuccess: () => setCompleting(false),
                    });
                }}
                processing={summary.processing}
            >
                <div className="grid gap-2">
                    <Label htmlFor="aud-summary">
                        {t('Summary')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Textarea
                        id="aud-summary"
                        rows={5}
                        value={summary.data.summary}
                        onChange={(e) =>
                            summary.setData('summary', e.target.value)
                        }
                    />
                    <InputError message={summary.errors.summary} />
                </div>
            </FormDialog>
        </>
    );
}

ShowAudit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Audits', href: auditRoutes.index() },
    ],
};
