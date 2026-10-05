// Laravel's LengthAwarePaginator as serialized to JSON.
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

export type TableFilters = {
    search?: string;
    sort_field?: string;
    sort_direction?: 'asc' | 'desc';
    per_page?: string | number;
    [key: string]: string | number | undefined;
};
