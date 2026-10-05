import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';

export function PageHeader({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 className="text-2xl font-bold">{t(title)}</h1>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {t(description)}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}
