<?php

declare(strict_types=1);

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * The shared list contract (plan section 7.3):
 *   { data: [...], meta: { page, per_page, total, last_page, summary? }, links }
 * Query parameters: page, per_page (max 100), sort (field|-field), q, filter[name]=value
 */
final class ApiList
{
    public const int MAX_PER_PAGE = 100;

    /**
     * Apply page/per_page/sort/q to a query builder and paginate.
     * Accepts an Eloquent builder or a base query builder.
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     * @param TBuilder $query
     * @param array<string, string> $sortable Allowed sort columns (map request name => column)
     * @param callable|null $search Applies the free-text `q` filter
     * @param array<string, callable(TBuilder, mixed): void> $filters
     */
    public static function paginate(
        EloquentBuilder|QueryBuilder $query,
        Request $request,
        array $sortable = [],
        ?callable $search = null,
        array $filters = [],
    ): LengthAwarePaginator {
        $perPage = min((int) $request->query('per_page', '25'), self::MAX_PER_PAGE);
        $perPage = max($perPage, 1);

        foreach ($filters as $param => $applier) {
            if ($request->filled($param)) {
                $applier($query, $request->query($param));
            }
        }

        $filterBag = $request->query('filter', []);
        if (is_array($filterBag)) {
            foreach ($filterBag as $name => $value) {
                if ($value === null || $value === '' || $value === 'all') {
                    continue;
                }
                $query->where((string) $name, $value);
            }
        }

        if ($search !== null && $request->filled('q')) {
            $search($query, (string) $request->query('q'));
        }

        $sort = (string) $request->query('sort', '');
        if ($sort !== '' && $sortable !== []) {
            $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $field = ltrim($sort, '-');
            if (isset($sortable[$field])) {
                $query->orderBy($sortable[$field], $dir);
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Shape a paginator into the list envelope, with an optional summary
     * computed from the un-paginated clone of the query.
     *
     * @param LengthAwarePaginator $paginator
     * @param callable|null $summary
     * @return array{data: mixed, meta: array<string, mixed>, links: array<string, mixed>}
     */
    public static function envelope(LengthAwarePaginator $paginator, ?callable $summary = null): array
    {
        $meta = [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
        if ($summary !== null) {
            $meta['summary'] = $summary();
        }

        return [
            'data' => $paginator->getCollection(),
            'meta' => $meta,
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ];
    }
}
