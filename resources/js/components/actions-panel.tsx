import { useForm } from '@inertiajs/react';
import { ListPlus, Plus } from 'lucide-react';
import { useState } from 'react';
import { ActionControls } from '@/components/action-controls';
import { DatePicker } from '@/components/date-picker';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { DateCell, IdBadge } from '@/components/table-cells';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import { CONTROL_LEVELS, PRIORITIES, labelOf } from '@/lib/hse';
import type { Action } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

const blank = {
    description: '',
    control_level: 'engineering',
    priority: 'medium',
    owner_id: '',
    due_on: '',
};

/**
 * The corrective actions of one source record (observation, incident, ...), with the
 * "Raise action" dialog and each action's done/verify buttons.
 */
export function ActionsPanel({
    actions,
    owners,
    sourceNumber,
    store,
    open,
    emptyText,
}: {
    actions: Action[];
    owners: { id: number; name: string }[];
    sourceNumber: string;
    /** The source's actions.store route. */
    store: RouteDefinition<'post'>;
    /** Whether the source still takes new actions. */
    open: boolean;
    emptyText: string;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const [adding, setAdding] = useState(false);
    const form = useForm(blank);

    return (
        <section className="grid gap-3 rounded-xl border p-5">
            <div className="flex items-center justify-between gap-2">
                <h2 className="font-medium">{t('Corrective actions')}</h2>
                {can('create-actions') && open && (
                    <Button
                        size="sm"
                        onClick={() => {
                            form.setData(blank);
                            form.clearErrors();
                            setAdding(true);
                        }}
                    >
                        <Plus /> {t('Raise action')}
                    </Button>
                )}
            </div>
            {actions.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t(emptyText)}</p>
            ) : (
                <ul className="divide-y">
                    {actions.map((a) => (
                        <li
                            key={a.id}
                            className="grid gap-2 py-3 first:pt-0 last:pb-0"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <IdBadge>{a.number}</IdBadge>
                                <StatusBadge status={a.status} />
                                <StatusBadge
                                    status={a.priority}
                                    label={t(':p priority', { p: a.priority })}
                                />
                                <span className="text-xs text-muted-foreground">
                                    {t(
                                        labelOf(
                                            CONTROL_LEVELS,
                                            a.control_level,
                                        ),
                                    )}
                                </span>
                            </div>
                            <p className="text-sm whitespace-pre-line">
                                {a.description}
                            </p>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                <span>{a.owner.name}</span>
                                <DateCell value={a.due_on} />
                            </div>
                            {a.rejection_reason && a.status === 'open' && (
                                <p className="text-sm text-red-700 dark:text-red-400">
                                    {t('Sent back: :r', {
                                        r: a.rejection_reason,
                                    })}
                                </p>
                            )}
                            {a.completion_notes && a.status !== 'open' && (
                                <p className="text-sm">
                                    <span className="text-muted-foreground">
                                        {t('Done:')}{' '}
                                    </span>
                                    {a.completion_notes}
                                </p>
                            )}
                            {a.verifier && (
                                <p className="text-sm text-muted-foreground">
                                    {t('Verified by :name', {
                                        name: a.verifier.name,
                                    })}
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2">
                                <ActionControls action={a} />
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <FormDialog
                open={adding}
                onOpenChange={setAdding}
                title="Raise action"
                description={t(
                    ':number: give it an owner and a due date. The owner marks it done; someone else verifies it.',
                    { number: sourceNumber },
                )}
                icon={ListPlus}
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(store, {
                        preserveScroll: true,
                        onSuccess: () => setAdding(false),
                    });
                }}
                processing={form.processing}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="action-description">
                            {t('What needs to be done?')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="action-description"
                            rows={3}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="action-control">
                            {t('Type of control')}
                        </Label>
                        <SelectField
                            id="action-control"
                            value={form.data.control_level}
                            onChange={(e) =>
                                form.setData('control_level', e.target.value)
                            }
                        >
                            {CONTROL_LEVELS.map((c) => (
                                <option key={c.value} value={c.value}>
                                    {t(c.label)}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.control_level} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="action-priority">{t('Priority')}</Label>
                        <SelectField
                            id="action-priority"
                            value={form.data.priority}
                            onChange={(e) =>
                                form.setData('priority', e.target.value)
                            }
                        >
                            {PRIORITIES.map((p) => (
                                <option key={p} value={p}>
                                    {t(p.charAt(0).toUpperCase() + p.slice(1))}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.priority} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="action-owner">
                            {t('Owner')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <SelectField
                            id="action-owner"
                            value={form.data.owner_id}
                            onChange={(e) =>
                                form.setData('owner_id', e.target.value)
                            }
                        >
                            <option value="">{t('Select person')}</option>
                            {owners.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </SelectField>
                        <InputError message={form.errors.owner_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="action-due">
                            {t('Due')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            id="action-due"
                            value={form.data.due_on}
                            onChange={(value) => form.setData('due_on', value)}
                        />
                        <InputError message={form.errors.due_on} />
                    </div>
                </div>
            </FormDialog>
        </section>
    );
}
