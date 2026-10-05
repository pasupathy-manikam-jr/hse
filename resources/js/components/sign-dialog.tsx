import { useForm } from '@inertiajs/react';
import { PenLine } from 'lucide-react';
import { FormDialog } from '@/components/form-dialog';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Electronic signature: the signer re-enters their password, and the server records who signed,
 * what it meant and when. Also used for steps that only need a reason (no password).
 */
export function SignDialog({
    open,
    onOpenChange,
    title,
    description,
    url,
    data,
    signed = true,
    reason = false,
    submitLabel = 'Sign',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    /** PUT endpoint. */
    url: string;
    /** Extra fields sent with it (e.g. the new status). */
    data: Record<string, string>;
    signed?: boolean;
    reason?: boolean;
    submitLabel?: string;
}) {
    const { t } = useTranslation();
    const form = useForm({ password: '', reason: '' });

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => {
                form.reset();
                form.clearErrors();
                onOpenChange(next);
            }}
            title={title}
            description={description}
            icon={PenLine}
            submitLabel={submitLabel}
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({ ...data, ...d }));
                form.put(url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onOpenChange(false);
                    },
                    // A wrong password keeps the dialog open; other errors show on the page.
                    onError: (errors) => {
                        if (!errors.password && !errors.reason) {
                            onOpenChange(false);
                        }
                    },
                });
            }}
        >
            <div className="grid gap-4">
                {reason && (
                    <div className="grid gap-2">
                        <Label htmlFor="sign-reason">
                            {t('Reason')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="sign-reason"
                            rows={3}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                )}
                {signed && (
                    <div className="grid gap-2">
                        <Label htmlFor="sign-password">
                            {t('Your password')}
                            <span className="text-destructive">*</span>
                        </Label>
                        <PasswordInput
                            id="sign-password"
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                        <InputError message={form.errors.password} />
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Re-entering your password signs this step in your name.',
                            )}
                        </p>
                    </div>
                )}
            </div>
        </FormDialog>
    );
}
