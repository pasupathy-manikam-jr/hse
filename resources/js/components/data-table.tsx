import { router } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Filter,
    LayoutGrid,
    List,
    Search,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SelectField } from '@/components/select-field';
import { useTranslation } from '@/hooks/use-translation';
import { cn, toUrl } from '@/lib/utils';
import type { Paginated, TableFilters } from '@/types';

export type Column<T> = {
    key: string;
    label: string;
    /** Server-side sort key; omit for unsortable columns. */
    sortable?: boolean;
    className?: string;
    render: (row: T) => ReactNode;
};

const PER_PAGE = [10, 25, 50, 100];

/** Page numbers around the current page, with gaps: 1 … 4 5 6 … 12. */
function pageNumbers(current: number, last: number): (number | '…')[] {
    const pages = new Set(
        [1, last, current - 1, current, current + 1].filter(
            (page) => page >= 1 && page <= last,
        ),
    );
    const sorted = [...pages].sort((a, b) => a - b);

    return sorted.flatMap((page, i) =>
        i > 0 && page - sorted[i - 1] > 1 ? ['…' as const, page] : [page],
    );
}

/**
 * Server-driven list in the demo's layout: a toolbar card (search, inline filters,
 * optional "Filters" panel, list/grid toggle, status tabs), then a table or a card
 * grid, then numbered pagination. Search, sort, page size, page and view live in
 * the query string, and TableQuery (app/Support/TableQuery.php) applies them.
 */
export function DataTable<T extends { id: number }>({
    data,
    columns,
    filters,
    url,
    actions,
    toolbar,
    moreFilters,
    tabs,
    renderCard,
    cardsOnly = false,
    emptyMessage = 'No records found',
}: {
    data: Paginated<T>;
    columns: Column<T>[];
    filters: TableFilters;
    url: NonNullable<InertiaLinkProps['href']>;
    actions?: (row: T) => ReactNode;
    /** Inline filter controls, shown next to the search box. */
    toolbar?: ReactNode;
    /** Extra filters revealed by the "Filters" button (which always offers "Clear Filters"). */
    moreFilters?: ReactNode;
    /** Status tabs (usually <StatusTabs />), shown under the filters. */
    tabs?: ReactNode;
    /** Card for the grid view; the list/grid toggle only appears when given. */
    renderCard?: (row: T, actions: ReactNode) => ReactNode;
    /** Always show the cards (pages the demo only draws as cards); hides the list/grid toggle. */
    cardsOnly?: boolean;
    emptyMessage?: string;
}) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [showMore, setShowMore] = useState(false);
    const first = useRef(true);
    const view =
        renderCard && (cardsOnly || filters.view === 'grid') ? 'grid' : 'list';

    const visit = (changes: TableFilters) =>
        router.get(
            toUrl(url),
            Object.fromEntries(
                Object.entries({ ...filters, ...changes }).filter(
                    ([, value]) => value !== '' && value !== undefined,
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    // Debounced search; resets to page 1.
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => visit({ search, page: undefined }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    // Everything except the page size and list/grid choice counts as a filter.
    const filtered = Object.entries(filters).some(
        ([key, value]) =>
            ![
                'per_page',
                'view',
                'page',
                'sort_field',
                'sort_direction',
            ].includes(key) &&
            value !== null &&
            value !== undefined &&
            value !== '',
    );

    const clearFilters = () => {
        setSearch('');
        router.get(
            toUrl(url),
            Object.fromEntries(
                Object.entries({
                    per_page: filters.per_page,
                    view: filters.view,
                }).filter(([, value]) => value !== undefined && value !== null),
            ),
            { preserveScroll: true, replace: true },
        );
    };

    const sort = (key: string) =>
        visit({
            sort_field: key,
            sort_direction:
                filters.sort_field === key && filters.sort_direction === 'asc'
                    ? 'desc'
                    : 'asc',
        });

    const SortIcon = ({ column }: { column: string }) =>
        filters.sort_field !== column ? (
            <ArrowUpDown className="size-3.5 opacity-50" />
        ) : filters.sort_direction === 'asc' ? (
            <ArrowUp className="size-3.5" />
        ) : (
            <ArrowDown className="size-3.5" />
        );

    const empty = (
        <div className="px-4 py-12 text-center text-muted-foreground">
            {t(emptyMessage)}
        </div>
    );

    return (
        <div className="grid gap-4">
            <div className="rounded-xl border bg-card shadow-sm">
                <div className="flex flex-wrap items-center gap-3 p-3">
                    <div className="relative w-full max-w-xs">
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search...')}
                            aria-label={t('Search')}
                            className="ps-9"
                        />
                    </div>
                    {toolbar}
                    <div className="ms-auto flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            aria-expanded={showMore}
                            onClick={() => setShowMore((open) => !open)}
                        >
                            <Filter /> {t('Filters')}
                        </Button>
                        {renderCard && !cardsOnly && (
                            <div
                                role="group"
                                aria-label={t('View')}
                                className="flex rounded-md border p-0.5"
                            >
                                {(
                                    [
                                        ['list', List, 'List view'],
                                        ['grid', LayoutGrid, 'Grid view'],
                                    ] as const
                                ).map(([mode, Icon, label]) => (
                                    <button
                                        key={mode}
                                        type="button"
                                        aria-label={t(label)}
                                        aria-pressed={view === mode}
                                        onClick={() =>
                                            visit({
                                                view:
                                                    mode === 'grid'
                                                        ? 'grid'
                                                        : undefined,
                                            })
                                        }
                                        className={cn(
                                            'rounded px-2.5 py-1.5',
                                            view === mode
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:bg-muted',
                                        )}
                                    >
                                        <Icon className="size-4" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
                {showMore && (
                    <div className="flex flex-wrap items-center gap-3 border-t p-3">
                        {moreFilters}
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={!filtered}
                            onClick={clearFilters}
                        >
                            <X /> {t('Clear Filters')}
                        </Button>
                    </div>
                )}
                {tabs && <div className="border-t px-3">{tabs}</div>}
            </div>

            {view === 'grid' && renderCard ? (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    {data.data.map((row) => (
                        <div key={row.id}>
                            {renderCard(
                                row,
                                actions ? (
                                    <div className="flex justify-end gap-1">
                                        {actions(row)}
                                    </div>
                                ) : null,
                            )}
                        </div>
                    ))}
                    {data.data.length === 0 && (
                        <div className="col-span-full rounded-xl border bg-card">
                            {empty}
                        </div>
                    )}
                </div>
            ) : (
                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/60 text-muted-foreground">
                                <tr>
                                    <th className="w-12 px-4 py-3 text-start font-medium">
                                        #
                                    </th>
                                    {columns.map((column) => (
                                        <th
                                            key={column.key}
                                            className={cn(
                                                'px-4 py-3 text-start font-medium',
                                                column.className,
                                            )}
                                            aria-sort={
                                                filters.sort_field ===
                                                column.key
                                                    ? filters.sort_direction ===
                                                      'asc'
                                                        ? 'ascending'
                                                        : 'descending'
                                                    : undefined
                                            }
                                        >
                                            {column.sortable ? (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        sort(column.key)
                                                    }
                                                    className="inline-flex items-center gap-1 hover:text-foreground"
                                                >
                                                    {t(column.label)}
                                                    <SortIcon
                                                        column={column.key}
                                                    />
                                                </button>
                                            ) : (
                                                t(column.label)
                                            )}
                                        </th>
                                    ))}
                                    {actions && (
                                        <th className="px-4 py-3 text-end font-medium">
                                            {t('Actions')}
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {data.data.map((row, index) => (
                                    <tr
                                        key={row.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {(data.from ?? 1) + index}
                                        </td>
                                        {columns.map((column) => (
                                            <td
                                                key={column.key}
                                                className={cn(
                                                    'px-4 py-3',
                                                    column.className,
                                                )}
                                            >
                                                {column.render(row)}
                                            </td>
                                        ))}
                                        {actions && (
                                            <td className="px-4 py-2">
                                                <div className="flex justify-end gap-1">
                                                    {actions(row)}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {data.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={
                                                columns.length +
                                                (actions ? 2 : 1)
                                            }
                                        >
                                            {empty}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card px-4 py-3 text-sm text-muted-foreground shadow-sm">
                <span>
                    {t('Showing :from to :to of :total results', {
                        from: data.from ?? 0,
                        to: data.to ?? 0,
                        total: data.total,
                    })}
                </span>
                <div className="flex flex-wrap items-center gap-3">
                    <label className="flex items-center gap-2">
                        {t('Rows per page')}:
                        <SelectField
                            value={data.per_page}
                            onChange={(e) =>
                                visit({
                                    per_page: e.target.value,
                                    page: undefined,
                                })
                            }
                            className="h-8 w-20"
                        >
                            {PER_PAGE.map((n) => (
                                <option key={n} value={n}>
                                    {n}
                                </option>
                            ))}
                        </SelectField>
                    </label>
                    <nav
                        aria-label={t('Pagination')}
                        className="flex items-center gap-1"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={data.current_page <= 1}
                            onClick={() =>
                                visit({ page: data.current_page - 1 })
                            }
                        >
                            « {t('Previous')}
                        </Button>
                        {pageNumbers(data.current_page, data.last_page).map(
                            (page, i) =>
                                page === '…' ? (
                                    <span key={`gap-${i}`} className="px-1">
                                        …
                                    </span>
                                ) : (
                                    <Button
                                        key={page}
                                        size="sm"
                                        variant={
                                            page === data.current_page
                                                ? 'default'
                                                : 'outline'
                                        }
                                        aria-current={
                                            page === data.current_page
                                                ? 'page'
                                                : undefined
                                        }
                                        onClick={() => visit({ page })}
                                    >
                                        {page}
                                    </Button>
                                ),
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={data.current_page >= data.last_page}
                            onClick={() =>
                                visit({ page: data.current_page + 1 })
                            }
                        >
                            {t('Next')} »
                        </Button>
                    </nav>
                </div>
            </div>
        </div>
    );
}
