<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Search, sort and paginate a list page the same way everywhere
 * (query params: search, sort_field, sort_direction, per_page, page).
 */
class TableQuery
{
    public const PER_PAGE = [10, 25, 50, 100];

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $searchable  columns matched with LIKE
     * @param  list<string>  $sortable  columns the client may sort by
     * @return LengthAwarePaginator<int, TModel>
     */
    public static function paginate(Builder $query, Request $request, array $searchable, array $sortable, string $defaultSort = 'created_at'): LengthAwarePaginator
    {
        if ($search = trim($request->string('search')->toString())) {
            $query->where(function (Builder $q) use ($searchable, $search) {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'like', '%'.addcslashes($search, '%_\\').'%');
                }
            });
        }

        $field = in_array($request->input('sort_field'), $sortable, true) ? $request->input('sort_field') : $defaultSort;
        $direction = $request->input('sort_direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : self::PER_PAGE[0];

        return $query->orderBy($field, $direction)->orderBy('id', $direction)->paginate($perPage)->withQueryString();
    }

    /**
     * Row counts per value of a column (status tabs), over the query's filters only.
     *
     * Selected columns, eager counts and ordering are dropped first: MySQL's
     * only_full_group_by rejects "table.*" or withCount() sub-selects next to GROUP BY.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Collection<array-key, int>
     */
    public static function countBy(Builder $query, string $column): Collection
    {
        return (clone $query)->toBase()->reorder()
            ->select($column)->selectRaw('count(*) as total')
            ->groupBy($column)
            ->pluck('total', $column)
            ->map(fn ($total) => (int) $total);
    }

    /**
     * The filter values echoed back to the page.
     *
     * @param  list<string>  $extra  module-specific filter keys
     * @return array<string, mixed>
     */
    public static function filters(Request $request, array $extra = []): array
    {
        return $request->only(['search', 'sort_field', 'sort_direction', 'per_page', 'view', ...$extra]);
    }
}
