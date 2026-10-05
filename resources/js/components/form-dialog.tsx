import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Create/edit dialog shell: title, fields (children), Cancel / Save.
 */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    onSubmit,
    processing,
    submitLabel = 'Save',
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    onSubmit: (e: FormEvent) => void;
    processing: boolean;
    submitLabel?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {/* Header and footer stay put; only the fields scroll on long forms. */}
            <DialogContent className="flex max-h-[90dvh] flex-col sm:max-w-2xl">
                <form
                    noValidate
                    onSubmit={onSubmit}
                    className="flex min-h-0 flex-1 flex-col gap-4"
                >
                    <DialogHeader>
                        <DialogTitle>{t(title)}</DialogTitle>
                        {description && (
                            <DialogDescription>
                                {t(description)}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    <div className="-mx-6 grid min-h-0 flex-1 gap-4 overflow-y-auto px-6 py-1">
                        {children}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t(submitLabel)}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
