import { router } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarX,
    Circle,
    CircleAlert,
    CircleCheck,
    CircleX,
    Clock,
    FileText,
    LayoutGrid,
    Loader,
    UserX,
    RotateCcw,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { SelectField } from '@/components/select-field';
import { useTranslation } from '@/hooks/use-translation';
import { cn, toUrl } from '@/lib/utils';
import type { TableFilters } from '@/types';

type Href = NonNullable<InertiaLinkProps['href']>;

/**
 * Apply module filters (branch, status, ...) on top of the DataTable's query string; resets to page 1.
 */
export function applyFilters(
    url: Href,
    filters: TableFilters,
    changes: TableFilters,
) {
    router.get(
        toUrl(url),
        Object.fromEntries(
            Object.entries({ ...filters, ...changes, page: undefined }).filter(
                ([, value]) => value !== '' && value !== undefined,
            ),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

/** A labelled "All X" select bound to one query-string filter. */
export function FilterSelect({
    url,
    filters,
    name,
    label,
    options,
}: {
    url: Href;
    filters: TableFilters;
    name: string;
    label: string;
    options: { id: number | string; name: string }[];
}) {
    const { t } = useTranslation();

    return (
        <SelectField
            aria-label={t(label)}
            className="w-auto max-w-56 min-w-40"
            value={filters[name] ?? ''}
            onChange={(e) =>
                applyFilters(url, filters, { [name]: e.target.value })
            }
        >
            <option value="">{t(label)}</option>
            {options.map((option) => (
                <option key={option.id} value={option.id}>
                    {option.name}
                </option>
            ))}
        </SelectField>
    );
}

// Icon per common status; anything else gets a neutral dot.
const STATUS_ICONS: Record<string, LucideIcon> = {
    all: LayoutGrid,
    active: CircleCheck,
    approved: CircleCheck,
    completed: CircleCheck,
    published: CircleCheck,
    hired: CircleCheck,
    present: CircleCheck,
    pass: CircleCheck,
    acknowledged: CircleCheck,
    issued: CircleAlert,
    inactive: CircleX,
    rejected: CircleX,
    cancelled: CircleX,
    declined: CircleX,
    fail: CircleX,
    terminated: UserX,
    probation: Clock,
    pending: Clock,
    pending_approval: Clock,
    renewed: RotateCcw,
    in_progress: Clock,
    scheduled: Clock,
    upcoming: Clock,
    planned: CalendarClock,
    ongoing: Loader,
    draft: FileText,
    expired: CalendarX,
};

/** The demo's underlined status tabs with icons and count pills; filters by status (or another `name` filter). */
export function StatusTabs({
    url,
    filters,
    counts,
    name = 'status',
}: {
    url: Href;
    filters: TableFilters;
    counts: Record<string, number>;
    name?: string;
}) {
    const { t } = useTranslation();
    const current = String(filters[name] ?? 'all');

    return (
        <div role="tablist" className="-mb-px flex flex-wrap gap-x-2">
            {Object.entries(counts).map(([status, count]) => {
                const selected = current === status;
                const Icon =
                    STATUS_ICONS[status.toLowerCase().replace(/ /g, '_')] ??
                    Circle;

                return (
                    <button
                        key={status}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        onClick={() =>
                            applyFilters(url, filters, {
                                [name]: status === 'all' ? undefined : status,
                            })
                        }
                        className={cn(
                            'flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors',
                            selected
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Icon className="size-4" />
                        {t(
                            status
                                .replace(/_/g, ' ')
                                .replace(/\b\w/g, (c) => c.toUpperCase()),
                        )}
                        <span
                            className={cn(
                                'min-w-5 rounded-full px-1.5 text-xs',
                                selected
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {count}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}

/** "From" / "To" date inputs bound to the date_from and date_to filters. */
export function DateRangeFilter({
    url,
    filters,
}: {
    url: Href;
    filters: TableFilters;
}) {
    const { t } = useTranslation();

    return (
        <>
            {(
                [
                    ['date_from', 'From'],
                    ['date_to', 'To'],
                ] as const
            ).map(([name, label]) => (
                <Input
                    key={name}
                    type="date"
                    aria-label={t(label)}
                    title={t(label)}
                    className="w-auto"
                    value={filters[name] ?? ''}
                    onChange={(e) =>
                        applyFilters(url, filters, { [name]: e.target.value })
                    }
                />
            ))}
        </>
    );
}
