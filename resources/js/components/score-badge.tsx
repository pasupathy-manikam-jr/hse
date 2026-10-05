import { cn } from '@/lib/utils';

/** The score as a coloured percentage: 90+ good, 70+ fair, below that poor. */
export function ScoreBadge({ score }: { score: number | null }) {
    if (score === null) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span
            className={cn(
                'font-semibold tabular-nums',
                score >= 90
                    ? 'text-emerald-700 dark:text-emerald-400'
                    : score >= 70
                      ? 'text-amber-700 dark:text-amber-400'
                      : 'text-red-700 dark:text-red-400',
            )}
        >
            {score}%
        </span>
    );
}
