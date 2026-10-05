import { useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { LIKELIHOOD, SEVERITY, riskBand } from '@/lib/hse';
import { cn } from '@/lib/utils';
import type { RiskHazard } from '@/types';

const CELL: Record<string, string> = {
    low: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    medium: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    high: 'bg-orange-200 text-orange-950 dark:bg-orange-900 dark:text-orange-100',
    extreme: 'bg-red-500 text-white dark:bg-red-700',
};

/** "12 · High": the score and its band, coloured like the matrix. */
export function RiskScore({ score }: { score: number }) {
    const { t } = useTranslation();
    const band = riskBand(score);

    return (
        <StatusBadge
            status={
                band === 'extreme'
                    ? 'extreme'
                    : band === 'low'
                      ? 'passed'
                      : band === 'medium'
                        ? 'medium'
                        : 'restricted'
            }
            label={`${score} · ${t(band.charAt(0).toUpperCase() + band.slice(1))}`}
        />
    );
}

/**
 * The 5×5 matrix (likelihood up, severity across) with how many hazards sit in each cell,
 * before or after the additional controls.
 */
export function RiskMatrix({ hazards }: { hazards: RiskHazard[] }) {
    const { t } = useTranslation();
    const [view, setView] = useState<'initial' | 'residual'>('residual');
    const count = (l: number, s: number) =>
        hazards.filter((h) =>
            view === 'initial'
                ? h.likelihood === l && h.severity === s
                : h.residual_likelihood === l && h.residual_severity === s,
        ).length;

    return (
        <div className="grid gap-3">
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={view}
                onValueChange={(v) => v && setView(v as typeof view)}
                aria-label={t('Matrix view')}
                className="justify-self-start"
            >
                <ToggleGroupItem value="initial" className="px-3">
                    {t('Before controls')}
                </ToggleGroupItem>
                <ToggleGroupItem value="residual" className="px-3">
                    {t('After controls')}
                </ToggleGroupItem>
            </ToggleGroup>
            <div className="grid grid-cols-[1.5rem_repeat(5,minmax(0,1fr))] gap-1 text-xs">
                {[...LIKELIHOOD].reverse().map((l) => (
                    <div key={l.value} className="contents">
                        <div
                            className="flex items-center justify-end pe-2 text-muted-foreground"
                            title={t(l.label)}
                        >
                            {l.value}
                        </div>
                        {SEVERITY.map((s) => {
                            const n = count(l.value, s.value);

                            return (
                                <div
                                    key={s.value}
                                    title={`${t(l.label)} × ${t(s.label)} = ${l.value * s.value}`}
                                    className={cn(
                                        'flex aspect-[3/2] items-center justify-center rounded-md text-sm font-semibold',
                                        CELL[riskBand(l.value * s.value)],
                                        n === 0 && 'opacity-60',
                                    )}
                                >
                                    {n > 0 ? n : ''}
                                </div>
                            );
                        })}
                    </div>
                ))}
                <div />
                {SEVERITY.map((s) => (
                    <div
                        key={s.value}
                        className="pt-1 text-center text-muted-foreground"
                        title={t(s.label)}
                    >
                        {s.value}
                    </div>
                ))}
            </div>
            <div className="grid gap-0.5 text-xs text-muted-foreground">
                <p>
                    {t('Likelihood (up):')}{' '}
                    {LIKELIHOOD.map((l) => `${l.value} ${t(l.label)}`).join(
                        ' · ',
                    )}
                </p>
                <p>
                    {t('Severity (across):')}{' '}
                    {SEVERITY.map((s) => `${s.value} ${t(s.label)}`).join(
                        ' · ',
                    )}
                </p>
                <p>{t('Numbers in the cells are hazards.')}</p>
            </div>
        </div>
    );
}
