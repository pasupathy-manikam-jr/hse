import { CircleHelp, TriangleAlert } from 'lucide-react';
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
import { cn } from '@/lib/utils';

/**
 * "Are you sure?" dialog. `destructive` (the default) is for deletes; `default` for other
 * one-way steps such as closing a record.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title = 'Are you sure?',
    description = 'This action cannot be undone.',
    confirmLabel = 'Delete',
    tone = 'destructive',
    onConfirm,
    processing = false,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title?: string;
    description?: string;
    confirmLabel?: string;
    tone?: 'destructive' | 'default';
    onConfirm: () => void;
    processing?: boolean;
}) {
    const { t } = useTranslation();
    const Icon = tone === 'destructive' ? TriangleAlert : CircleHelp;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                <DialogHeader className="flex-row items-start gap-4 px-6 pe-12 pt-6 pb-5 text-left">
                    <span
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-full',
                            tone === 'destructive'
                                ? 'bg-destructive/10 text-destructive'
                                : 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-400',
                        )}
                    >
                        <Icon className="size-5" />
                    </span>
                    <div className="grid gap-1.5">
                        <DialogTitle>{t(title)}</DialogTitle>
                        <DialogDescription>{t(description)}</DialogDescription>
                    </div>
                </DialogHeader>
                <DialogFooter className="border-t bg-muted/50 px-6 py-3">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button
                        variant={
                            tone === 'destructive' ? 'destructive' : 'default'
                        }
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        {t(confirmLabel)}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
