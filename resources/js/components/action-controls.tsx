import { router, useForm, usePage } from '@inertiajs/react';
import { CircleCheck, Send, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { useTranslation } from '@/hooks/use-translation';
import actionRoutes from '@/routes/actions';
import type { Action } from '@/types';

/**
 * The buttons that move an action on: its owner marks it done with evidence, then
 * someone else (with verify-actions) verifies it or sends it back. The server enforces the same rules.
 */
export function ActionControls({
    action,
    compact = false,
}: {
    action: Action;
    /** Icon-only buttons, for table rows. */
    compact?: boolean;
}) {
    const { t } = useTranslation();
    const can = useCan();
    const { auth } = usePage().props;
    const [mode, setMode] = useState<'complete' | 'reject' | null>(null);
    const form = useForm({ notes: '' });
    const isOwner = auth.user.id === action.owner_id;
    const canCheck =
        action.status === 'done' && !isOwner && can('verify-actions');

    const size = compact ? 'icon' : 'sm';
    const label = (text: string) =>
        compact ? { 'aria-label': t(text), title: t(text) } : {};
    const text = (value: string) => (compact ? null : t(value));

    const open = (next: 'complete' | 'reject') => {
        form.reset();
        form.clearErrors();
        setMode(next);
    };

    return (
        <>
            {isOwner && action.status === 'open' && (
                <Button
                    size={size}
                    {...label('Mark done')}
                    onClick={() => open('complete')}
                >
                    <Send /> {text('Mark done')}
                </Button>
            )}
            {canCheck && (
                <>
                    <Button
                        size={size}
                        {...label('Verify')}
                        onClick={() =>
                            router.put(
                                actionRoutes.verify(action.id),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <CircleCheck /> {text('Verify')}
                    </Button>
                    <Button
                        size={size}
                        variant="outline"
                        {...label('Send back')}
                        onClick={() => open('reject')}
                    >
                        <Undo2 /> {text('Send back')}
                    </Button>
                </>
            )}

            <FormDialog
                open={mode !== null}
                onOpenChange={(next) => !next && setMode(null)}
                title={
                    mode === 'complete'
                        ? t('Mark :number done', { number: action.number })
                        : t('Send :number back', { number: action.number })
                }
                onSubmit={(e) => {
                    e.preventDefault();
                    form.submit(
                        mode === 'complete'
                            ? actionRoutes.complete(action.id)
                            : actionRoutes.reject(action.id),
                        {
                            preserveScroll: true,
                            onSuccess: () => setMode(null),
                        },
                    );
                }}
                processing={form.processing}
                submitLabel={mode === 'complete' ? 'Mark done' : 'Send back'}
                description={
                    mode === 'complete'
                        ? 'Describe the evidence. Someone other than you will verify it.'
                        : 'The action goes back to its owner with your reason.'
                }
                icon={mode === 'complete' ? Send : Undo2}
            >
                <div className="grid gap-2">
                    <Label htmlFor={`action-notes-${action.id}`}>
                        {mode === 'complete'
                            ? t('What was done?')
                            : t('Why is it not done?')}
                        <span className="text-destructive">*</span>
                    </Label>
                    <Textarea
                        id={`action-notes-${action.id}`}
                        rows={4}
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                    />
                    <InputError message={form.errors.notes} />
                </div>
            </FormDialog>
        </>
    );
}
