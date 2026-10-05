import { TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';

export function ConfirmDialog({
    open,
    onOpenChange,
    title = 'Are you sure?',
    description = 'This action cannot be undone.',
    confirmLabel = 'Delete',
    onConfirm,
    processing = false,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title?: string;
    description?: string;
    confirmLabel?: string;
    onConfirm: () => void;
    processing?: boolean;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <div className="flex gap-4">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <TriangleAlert className="size-5" />
                    </span>
                    <DialogHeader className="m-0 border-0 p-0">
                        <DialogTitle>{t(title)}</DialogTitle>
                        <DialogDescription>{t(description)}</DialogDescription>
                    </DialogHeader>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('Cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={onConfirm}
                        disabled={processing}
                    >
                        {t(confirmLabel)}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
