import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ClipboardList,
    ListPlus,
    Plus,
    SquarePen,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { RESPONSE_TYPES, acceptable, labelOf } from '@/lib/hse';
import { dashboard } from '@/routes';
import checklistRoutes from '@/routes/checklists';

type Item = {
    id: number;
    question: string;
    response_type: string;
    min: string | null;
    max: string | null;
    critical: boolean;
};

type Template = {
    id: number;
    name: string;
    description: string | null;
    active: boolean;
    items: Item[];
};

const blankItem = {
    question: '',
    response_type: 'yes-no-na',
    min: '',
    max: '',
    critical: false,
};

export default function ShowChecklist({ template }: { template: Template }) {
    const { t } = useTranslation();
    const can = useCan();
    const editable = can('edit-checklists');
    const [itemFor, setItemFor] = useState<Item | 'new' | null>(null);
    const [removing, setRemoving] = useState<Item | null>(null);
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const item = useForm(blankItem);
    const details = useForm({
        name: template.name,
        description: template.description ?? '',
        active: template.active,
    });

    const openItem = (i: Item | null) => {
        item.clearErrors();
        item.setData(
            i
                ? {
                      question: i.question,
                      response_type: i.response_type,
                      min: i.min === null ? '' : String(Number(i.min)),
                      max: i.max === null ? '' : String(Number(i.max)),
                      critical: i.critical,
                  }
                : blankItem,
        );
        setItemFor(i ?? 'new');
    };

    return (
        <>
            <Head title={template.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={template.name}
                    description={template.description ?? undefined}
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" asChild>
                                <Link href={checklistRoutes.index()}>
                                    <ArrowLeft /> {t('Back')}
                                </Link>
                            </Button>
                            {editable && (
                                <Button
                                    variant="outline"
                                    onClick={() => setEditing(true)}
                                >
                                    <SquarePen /> {t('Edit')}
                                </Button>
                            )}
                            {can('delete-checklists') && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDeleting(true)}
                                >
                                    <Trash2 /> {t('Delete')}
                                </Button>
                            )}
                        </div>
                    }
                />

                <section className="grid max-w-4xl gap-3 rounded-xl border p-5">
                    <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <h2 className="font-medium">{t('Questions')}</h2>
                            <StatusBadge
                                status={template.active ? 'active' : 'inactive'}
                            />
                        </div>
                        {editable && (
                            <Button size="sm" onClick={() => openItem(null)}>
                                <Plus /> {t('Add question')}
                            </Button>
                        )}
                    </div>
                    {template.items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'No questions yet. A checklist needs at least one before it can be used.',
                            )}
                        </p>
                    ) : (
                        <ol className="divide-y">
                            {template.items.map((i, n) => (
                                <li
                                    key={i.id}
                                    className="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                >
                                    <div className="grid gap-1 text-sm">
                                        <span className="font-medium">
                                            {n + 1}. {i.question}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-2 text-muted-foreground">
                                            {t(
                                                labelOf(
                                                    RESPONSE_TYPES,
                                                    i.response_type,
                                                ),
                                            )}
                                            {acceptable(i) &&
                                                ` · ${acceptable(i)}`}
                                            {i.critical && (
                                                <StatusBadge
                                                    status="high"
                                                    label={t('Critical')}
                                                />
                                            )}
                                        </span>
                                    </div>
                                    {editable && (
                                        <div className="flex gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Edit')}
                                                onClick={() => openItem(i)}
                                            >
                                                <SquarePen />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={t('Remove')}
                                                onClick={() => setRemoving(i)}
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>

            <FormDialog
                open={itemFor !== null}
                onOpenChange={(open) => !open && setItemFor(null)}
                title={itemFor === 'new' ? 'Add question' : 'Edit question'}
                description="A failed answer raises an action when the inspection is completed. Critical ones are due the next day."
                icon={ListPlus}
                onSubmit={(e) => {
                    e.preventDefault();
                    item.submit(
                        itemFor === 'new' || itemFor === null
                            ? checklistRoutes.items.store(template.id)
                            : checklistRoutes.items.update([
                                  template.id,
                                  itemFor.id,
                              ]),
                        {
                            preserveScroll: true,
                            onSuccess: () => setItemFor(null),
                        },
                    );
                }}
                processing={item.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="q-question">
                            {t('Question')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="q-question"
                            placeholder={t('e.g. Are walkways clear?')}
                            value={item.data.question}
                            onChange={(e) =>
                                item.setData('question', e.target.value)
                            }
                        />
                        <InputError message={item.errors.question} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="q-type">{t('Answer type')}</Label>
                        <SelectField
                            id="q-type"
                            value={item.data.response_type}
                            onChange={(e) =>
                                item.setData((data) => ({
                                    ...data,
                                    response_type: e.target.value,
                                    min: '',
                                    max: '',
                                }))
                            }
                        >
                            {RESPONSE_TYPES.map((r) => (
                                <option key={r.value} value={r.value}>
                                    {t(r.label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={item.errors.response_type} />
                    </div>
                    {item.data.response_type === 'rating' && (
                        <div className="grid gap-2">
                            <Label htmlFor="q-min-rating">
                                {t('Lowest passing rating')}
                            </Label>
                            <Input
                                id="q-min-rating"
                                inputMode="numeric"
                                placeholder="3"
                                value={item.data.min}
                                onChange={(e) =>
                                    item.setData('min', e.target.value)
                                }
                            />
                            <InputError message={item.errors.min} />
                        </div>
                    )}
                    {item.data.response_type === 'number' && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="q-min">
                                    {t('Lowest acceptable')}
                                </Label>
                                <Input
                                    id="q-min"
                                    inputMode="decimal"
                                    value={item.data.min}
                                    onChange={(e) =>
                                        item.setData('min', e.target.value)
                                    }
                                />
                                <InputError message={item.errors.min} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="q-max">
                                    {t('Highest acceptable')}
                                </Label>
                                <Input
                                    id="q-max"
                                    inputMode="decimal"
                                    value={item.data.max}
                                    onChange={(e) =>
                                        item.setData('max', e.target.value)
                                    }
                                />
                                <InputError message={item.errors.max} />
                            </div>
                        </>
                    )}
                    <Label className="flex items-center gap-2 font-normal sm:col-span-2">
                        <Checkbox
                            checked={item.data.critical}
                            onCheckedChange={(checked) =>
                                item.setData('critical', checked === true)
                            }
                        />
                        {t('Critical: a failure is high priority')}
                    </Label>
                </div>
            </FormDialog>

            <FormDialog
                open={editing}
                onOpenChange={setEditing}
                title="Edit checklist"
                description="Inactive checklists can no longer be used for new inspections."
                icon={ClipboardList}
                onSubmit={(e) => {
                    e.preventDefault();
                    details.put(checklistRoutes.update.url(template.id), {
                        preserveScroll: true,
                        onSuccess: () => setEditing(false),
                    });
                }}
                processing={details.processing}
            >
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="cl-name">
                            {t('Name')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="cl-name"
                            value={details.data.name}
                            onChange={(e) =>
                                details.setData('name', e.target.value)
                            }
                        />
                        <InputError message={details.errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cl-description">
                            {t('Description')}
                        </Label>
                        <Textarea
                            id="cl-description"
                            rows={2}
                            value={details.data.description}
                            onChange={(e) =>
                                details.setData('description', e.target.value)
                            }
                        />
                    </div>
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={details.data.active}
                            onCheckedChange={(checked) =>
                                details.setData('active', checked === true)
                            }
                        />
                        {t('Active')}
                    </Label>
                </div>
            </FormDialog>

            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Remove question"
                description={t('Remove ":q"? Past inspections keep it.', {
                    q: removing?.question ?? '',
                })}
                confirmLabel="Remove"
                onConfirm={() =>
                    removing &&
                    router.delete(
                        checklistRoutes.items.destroy([
                            template.id,
                            removing.id,
                        ]),
                        {
                            preserveScroll: true,
                            onSuccess: () => setRemoving(null),
                        },
                    )
                }
            />

            <ConfirmDialog
                open={deleting}
                onOpenChange={setDeleting}
                title="Delete checklist"
                description="The template is deleted. Inspections already done with it are kept."
                onConfirm={() =>
                    router.delete(checklistRoutes.destroy(template.id))
                }
            />
        </>
    );
}

ShowChecklist.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Checklists', href: checklistRoutes.index() },
    ],
};
