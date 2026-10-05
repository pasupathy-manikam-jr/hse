import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    CircleAlert,
    FilePen,
    FilePlus,
    FileText,
    RefreshCcw,
    Save,
    Send,
    Undo2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { DocumentFields } from '@/components/document-fields';
import type { DocumentFormData } from '@/components/document-fields';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PeoplePicker } from '@/components/people-picker';
import { SignDialog } from '@/components/sign-dialog';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useFormat } from '@/hooks/use-format';
import { useTranslation } from '@/hooks/use-translation';
import { DOCUMENT_TYPES, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import documentRoutes from '@/routes/documents';

type Person = { id: number; name: string };

type Revision = {
    id: number;
    revision: string;
    change_summary: string | null;
    status: 'draft' | 'in-review' | 'effective' | 'superseded';
    file_path: string | null;
    file_name: string | null;
    approved_at: string | null;
    created_by: number | null;
    approver: Person | null;
    creator: Person | null;
    readers: (Person & { pivot: { acknowledged_at: string | null } })[];
};

type Doc = {
    id: number;
    number: string;
    title: string;
    type: string;
    owner_id: number | null;
    review_interval_months: number;
    next_review_on: string | null;
    owner: Person | null;
    clauses: { id: number; number: string; title: string }[];
    revisions: Revision[];
};

function DraftForm({ doc, revision }: { doc: Doc; revision: Revision }) {
    const { t } = useTranslation();
    const form = useForm({
        change_summary: revision.change_summary ?? '',
        file: null as File | null,
    });

    return (
        <form
            noValidate
            className="grid gap-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(
                    documentRoutes.revisions.save.url([doc.id, revision.id]),
                    { forceFormData: true, preserveScroll: true },
                );
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor={`rev-summary-${revision.id}`}>
                    {t('What changed')}
                </Label>
                <Textarea
                    id={`rev-summary-${revision.id}`}
                    rows={2}
                    value={form.data.change_summary}
                    onChange={(e) =>
                        form.setData('change_summary', e.target.value)
                    }
                />
                <InputError message={form.errors.change_summary} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`rev-file-${revision.id}`}>
                    {revision.file_name
                        ? t('Replace file (now: :name)', {
                              name: revision.file_name,
                          })
                        : t('Document file')}
                </Label>
                <Input
                    id={`rev-file-${revision.id}`}
                    type="file"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                    onChange={(e) =>
                        form.setData('file', e.target.files?.[0] ?? null)
                    }
                />
                <InputError message={form.errors.file} />
            </div>
            <div className="flex flex-wrap justify-end gap-2">
                <Button
                    type="submit"
                    variant="outline"
                    size="sm"
                    disabled={form.processing}
                >
                    <Save /> {t('Save draft')}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    onClick={() =>
                        router.put(
                            documentRoutes.revisions.move.url([
                                doc.id,
                                revision.id,
                            ]),
                            { to: 'submit' },
                            { preserveScroll: true },
                        )
                    }
                >
                    <Send /> {t('Send for approval')}
                </Button>
            </div>
        </form>
    );
}

export default function ShowDocument({
    document: doc,
    people,
    clauses,
    reviewDue,
}: {
    document: Doc;
    people: Person[];
    clauses: { id: number; number: string; title: string }[];
    reviewDue: boolean;
}) {
    const { t } = useTranslation();
    const { date, dateTime } = useFormat();
    const can = useCan();
    const { errors, auth } = usePage().props as {
        errors: Record<string, string>;
        auth: { user: { id: number } };
    };
    const [editing, setEditing] = useState(false);
    const [approving, setApproving] = useState<Revision | null>(null);
    const [readersFor, setReadersFor] = useState<Revision | null>(null);
    const details = useForm<DocumentFormData>({
        number: doc.number,
        title: doc.title,
        type: doc.type,
        owner_id: String(doc.owner_id ?? ''),
        review_interval_months: String(doc.review_interval_months),
        clause_ids: doc.clauses.map((c) => c.id),
    });
    const readers = useForm({ user_ids: [] as number[] });
    const inWork = doc.revisions.some((r) =>
        ['draft', 'in-review'].includes(r.status),
    );
    const effective = doc.revisions.find((r) => r.status === 'effective');

    return (
        <>
            <Head title={doc.number} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${doc.number} · ${doc.title}`}
                    description={t(labelOf(DOCUMENT_TYPES, doc.type))}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={documentRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {can('edit-documents') && (
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(true)}
                                >
                                    <FilePen /> {t('Edit')}
                                </Button>
                            )}
                            {can('approve-documents') &&
                                reviewDue &&
                                effective && (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.put(
                                                documentRoutes.review.url(
                                                    doc.id,
                                                ),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <RefreshCcw />{' '}
                                        {t('Reviewed, no change')}
                                    </Button>
                                )}
                            {can('edit-documents') && !inWork && (
                                <Button
                                    onClick={() =>
                                        router.post(
                                            documentRoutes.revisions.store.url(
                                                doc.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <FilePlus /> {t('New revision')}
                                </Button>
                            )}
                        </div>
                    }
                />

                {(errors.status || errors.revision || errors.review) && (
                    <div
                        role="alert"
                        className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
                    >
                        <CircleAlert className="size-4 shrink-0" />
                        {errors.status ?? errors.revision ?? errors.review}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="grid content-start gap-4 rounded-xl border p-5">
                        <IdBadge>{doc.number}</IdBadge>
                        <dl className="grid gap-3 text-sm">
                            <div className="grid grid-cols-[7rem_1fr] gap-2">
                                <dt className="text-muted-foreground">
                                    {t('Owner')}
                                </dt>
                                <dd className="font-medium">
                                    {doc.owner?.name ?? '—'}
                                </dd>
                            </div>
                            <div className="grid grid-cols-[7rem_1fr] gap-2">
                                <dt className="text-muted-foreground">
                                    {t('In force')}
                                </dt>
                                <dd className="font-medium">
                                    {effective
                                        ? t('Rev :r', { r: effective.revision })
                                        : t('Not yet issued')}
                                </dd>
                            </div>
                            <div className="grid grid-cols-[7rem_1fr] gap-2">
                                <dt className="text-muted-foreground">
                                    {t('Next review')}
                                </dt>
                                <dd className="font-medium">
                                    <DateCell value={doc.next_review_on}>
                                        {reviewDue && (
                                            <StatusBadge
                                                status="pending"
                                                label={t('Due')}
                                            />
                                        )}
                                    </DateCell>
                                </dd>
                            </div>
                            <div className="grid grid-cols-[7rem_1fr] gap-2">
                                <dt className="text-muted-foreground">
                                    {t('Review every')}
                                </dt>
                                <dd className="font-medium">
                                    {t(':n months', {
                                        n: doc.review_interval_months,
                                    })}
                                </dd>
                            </div>
                        </dl>
                        {doc.clauses.length > 0 && (
                            <div className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">
                                    {t('ISO 45001')}
                                </span>
                                {doc.clauses.map((c) => (
                                    <span key={c.id}>
                                        §{c.number} {c.title}
                                    </span>
                                ))}
                            </div>
                        )}
                    </section>

                    <div className="grid content-start gap-4 lg:col-span-2">
                        {doc.revisions.map((r) => (
                            <section
                                key={r.id}
                                className="grid gap-3 rounded-xl border p-5"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="flex items-center gap-2">
                                        <h2 className="font-medium">
                                            {t('Revision :r', {
                                                r: r.revision,
                                            })}
                                        </h2>
                                        <StatusBadge status={r.status} />
                                    </div>
                                    {r.file_path && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <a
                                                href={documentRoutes.revisions.file.url(
                                                    r.id,
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <FileText />{' '}
                                                {r.file_name ?? t('Open')}
                                            </a>
                                        </Button>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {r.creator &&
                                        t('Written by :name', {
                                            name: r.creator.name,
                                        })}
                                    {r.approved_at &&
                                        ` · ${t('approved :t by :name', {
                                            t: dateTime(r.approved_at),
                                            name: r.approver?.name ?? '—',
                                        })}`}
                                </p>
                                {r.status === 'draft' &&
                                can('edit-documents') ? (
                                    <DraftForm doc={doc} revision={r} />
                                ) : (
                                    r.change_summary && (
                                        <p className="text-sm whitespace-pre-line">
                                            {r.change_summary}
                                        </p>
                                    )
                                )}
                                {r.status === 'in-review' &&
                                    can('approve-documents') && (
                                        <div className="flex flex-wrap justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.put(
                                                        documentRoutes.revisions.move.url(
                                                            [doc.id, r.id],
                                                        ),
                                                        { to: 'return' },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Undo2 /> {t('Return to draft')}
                                            </Button>
                                            {r.created_by !== auth.user.id && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        setApproving(r)
                                                    }
                                                >
                                                    <BadgeCheck />{' '}
                                                    {t('Approve')}
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                {r.status === 'effective' && (
                                    <div className="grid gap-2">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-medium">
                                                {t('Readers')}
                                            </span>
                                            {can('edit-documents') && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        readers.reset();
                                                        setReadersFor(r);
                                                    }}
                                                >
                                                    <UserPlus />{' '}
                                                    {t('Ask people to read')}
                                                </Button>
                                            )}
                                        </div>
                                        {r.readers.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                {t('Nobody asked yet.')}
                                            </p>
                                        ) : (
                                            <ul className="grid max-w-md gap-1 text-sm">
                                                {r.readers.map((p) => (
                                                    <li
                                                        key={p.id}
                                                        className="flex items-center justify-between gap-2"
                                                    >
                                                        {p.name}
                                                        {p.pivot
                                                            .acknowledged_at ? (
                                                            <StatusBadge
                                                                status="verified"
                                                                label={date(
                                                                    p.pivot
                                                                        .acknowledged_at,
                                                                )}
                                                            />
                                                        ) : (
                                                            <StatusBadge
                                                                status="pending"
                                                                label={t(
                                                                    'Not yet read',
                                                                )}
                                                            />
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                )}
                            </section>
                        ))}
                    </div>
                </div>
            </div>

            <FormDialog
                open={editing}
                onOpenChange={setEditing}
                title="Edit document"
                description="The number, title, owner, review interval and clauses."
                icon={FilePen}
                onSubmit={(e) => {
                    e.preventDefault();
                    details.put(documentRoutes.update.url(doc.id), {
                        preserveScroll: true,
                        onSuccess: () => setEditing(false),
                    });
                }}
                processing={details.processing}
            >
                <DocumentFields
                    form={details}
                    people={people}
                    clauses={clauses}
                />
            </FormDialog>

            {approving && (
                <SignDialog
                    open
                    onOpenChange={(open) => !open && setApproving(null)}
                    title={t('Approve revision :r', { r: approving.revision })}
                    description="It comes into force now and replaces the current revision."
                    url={documentRoutes.revisions.move.url([
                        doc.id,
                        approving.id,
                    ])}
                    data={{ to: 'approve' }}
                    submitLabel="Approve and sign"
                />
            )}

            <FormDialog
                open={readersFor !== null}
                onOpenChange={(open) => !open && setReadersFor(null)}
                title="Ask people to read"
                description="They see it under My reading and confirm once read."
                icon={UserPlus}
                onSubmit={(e) => {
                    e.preventDefault();
                    if (readersFor) {
                        readers.put(
                            documentRoutes.revisions.readers.url([
                                doc.id,
                                readersFor.id,
                            ]),
                            {
                                preserveScroll: true,
                                onSuccess: () => setReadersFor(null),
                            },
                        );
                    }
                }}
                processing={readers.processing}
            >
                <PeoplePicker
                    id="doc-readers"
                    people={people.filter(
                        (p) => !readersFor?.readers.some((r) => r.id === p.id),
                    )}
                    value={readers.data.user_ids}
                    onChange={(ids) => readers.setData('user_ids', ids)}
                />
                <InputError message={readers.errors.user_ids} />
            </FormDialog>
        </>
    );
}

ShowDocument.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Documents', href: documentRoutes.index() },
    ],
};
