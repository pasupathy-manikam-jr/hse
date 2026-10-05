import type { LucideIcon } from 'lucide-react';
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
 * Create/edit dialog shell: icon + title + description, scrolling fields, Cancel / Save bar.
 */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    icon: Icon,
    onSubmit,
    processing,
    submitLabel = 'Save',
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    icon?: LucideIcon;
    onSubmit: (e: FormEvent) => void;
    processing: boolean;
    submitLabel?: string;
    children: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90dvh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <form
                    noValidate
                    onSubmit={onSubmit}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <DialogHeader className="flex-row items-center gap-3 border-b px-6 py-4 pe-12 text-left">
                        {Icon && (
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-400">
                                <Icon className="size-5" />
                            </span>
                        )}
                        <div className="grid gap-1">
                            <DialogTitle>{t(title)}</DialogTitle>
                            {description ? (
                                <DialogDescription>
                                    {t(description)}
                                </DialogDescription>
                            ) : (
                                // Radix warns without a description; keep one for screen readers.
                                <DialogDescription className="sr-only">
                                    {t(title)}
                                </DialogDescription>
                            )}
                        </div>
                    </DialogHeader>
                    <div className="grid min-h-0 flex-1 gap-4 overflow-y-auto px-6 py-5">
                        {children}
                    </div>
                    <DialogFooter className="border-t bg-muted/50 px-6 py-3">
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
