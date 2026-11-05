<?php

declare(strict_types=1);

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * The shared list contract (plan section 7.3):
 *   { data: [...], meta: { page, per_page, total, last_page, summary?, sortable?, filters? }, links }
 * Query parameters: page, per_page (max 100), sort (field|-field), q, filter[name]=value
 *
 * Task6 T6-21/T6-30 contract:
 * - `sort` must name one of the endpoint's declared sortable keys; anything
 *   else is a 422 `sort.invalid` instead of being silently ignored.
 * - `meta.sortable` lists the accepted keys so the UI can disable arrows on
 *   columns the server cannot sort. A `raw:` value maps the key to a raw
 *   orderBy expression (computed columns: counts, percentages, subqueries).
 */
final class ApiList
{
    public const int MAX_PER_PAGE = 100;

    /** sortable keys of the paginate() call in this request — mirrored into meta.sortable (T6-21) */
    private static array $lastSortable = [];

    /**
     * Apply page/per_page/sort/q/filters to a query builder and paginate.
     * Accepts an Eloquent builder or a base query builder.
     *
     * @template TBuilder of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     * @param TBuilder $query
     * @param array<string, string> $sortable Allowed sort columns (map request name => column or "raw:" expression)
     * @param callable|null $search Applies the free-text `q` filter
     * @param array<string, callable(TBuilder, mixed): void> $filters quick params (legacy)
     * @param array<string, array{type:string, column?:string, options?:callable, lookup?:string}> $filterable
     *        T6-30 allowlist: the ONLY filter[name] keys accepted. Types:
     *        select | multiselect | text | boolean | number-range | date-range | lookup
     */
    public static function paginate(
        EloquentBuilder|QueryBuilder $query,
        Request $request,
        array $sortable = [],
        ?callable $search = null,
        array $filters = [],
        array $filterable = [],
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
                // T6-30: an allowlist replaces the generic filter[column]=value —
                // unknown keys fail with 422 filter.invalid, never leak columns
                if ($filterable !== []) {
                    if (! isset($filterable[(string) $name])) {
                        abort(response()->json([
                            'message' => 'عامل تصفية غير مدعوم: ' . $name,
                            'code' => 'filter.invalid',
                            'errors' => ['filter' => ['allowed' => array_keys($filterable)]],
                            'timestamp' => now()->toIso8601ZuluString(),
                        ], 422));
                    }
                    self::applyDeclaredFilter($query, (string) $name, $filterable[(string) $name], $value);
                } else {
                    $query->where((string) $name, $value);
                }
            }
        }

        if ($search !== null && $request->filled('q')) {
            $search($query, (string) $request->query('q'));
        }

        $sort = (string) $request->query('sort', '');
        if ($sort !== '') {
            $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $field = ltrim($sort, '-');
            // T6-21: a sort key the endpoint does not declare is a client bug —
            // fail loudly with the accepted keys instead of ignoring it
            if (! isset($sortable[$field])) {
                abort(response()->json([
                    'message' => 'مفتاح ترتيب غير مدعوم: ' . $field,
                    'code' => 'sort.invalid',
                    'errors' => ['sort' => ['sortable' => array_keys($sortable)]],
                    'timestamp' => now()->toIso8601ZuluString(),
                ], 422));
            }
            $column = $sortable[$field];
            if (is_string($column) && str_starts_with($column, 'raw:')) {
                $query->orderByRaw(substr($column, 4) . " {$dir}");
            } else {
                $query->orderBy($column, $dir);
            }
        }

        self::$lastSortable = array_keys($sortable);
        self::$lastFilterable = $filterable;

        return $query->paginate($perPage)->withQueryString();
    }

    /** @var array<string, array{type:string, column?:string, options?:callable, lookup?:string}> */
    private static array $lastFilterable = [];

    /**
     * Apply one declared filter (T6-30). Scalar types become where/whereIn;
     * ranges read filter[key][min|max] or [from|to] with validation.
     * @param EloquentBuilder|QueryBuilder $query
     */
    private static function applyDeclaredFilter(EloquentBuilder|QueryBuilder $query, string $name, array $decl, mixed $value): void
    {
        $column = $decl['column'] ?? $name;
        $type = $decl['type'] ?? 'select';

        if (( $type === 'number-range' || $type === 'date-range') && is_array($value)) {
            if ($type === 'number-range') {
                $min = isset($value['min']) && is_numeric($value['min']) ? (float) $value['min'] : null;
                $max = isset($value['max']) && is_numeric($value['max']) ? (float) $value['max'] : null;
                $query->whereBetween($column, [$min ?? PHP_FLOAT_MIN, $max ?? PHP_FLOAT_MAX]);
            } else {
                $from = isset($value['from']) && strtotime((string) $value['from']) ? (string) $value['from'] : null;
                $to = isset($value['to']) && strtotime((string) $value['to']) ? (string) $value['to'] : null;
                if ($from !== null && $to !== null) {
                    $query->whereBetween($column, [$from, $to]);
                } elseif ($from !== null) {
                    $query->where($column, '>=', $from);
                } elseif ($to !== null) {
                    $query->where($column, '<=', $to);
                }
            }
            return;
        }

        if ($type === 'multiselect') {
            $values = is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)));
            $query->whereIn($column, $values);
            return;
        }

        if ($type === 'boolean') {
            $query->where($column, filter_var($value, FILTER_VALIDATE_BOOLEAN));
            return;
        }

        $query->where($column, $value);
    }

    /**
     * The filter definitions as T6-30 JSON for meta.filters — options
     * callbacks resolved — so the UI and the server can never disagree.
     * @return array<int, array<string, mixed>>
     */
    public static function filterDefs(): array
    {
        $defs = [];
        foreach (self::$lastFilterable as $key => $decl) {
            $def = [
                'key' => $key,
                'label' => 'filter.' . $key,
                'type' => $decl['type'] ?? 'select',
                'field' => $decl['column'] ?? $key,
            ];
            if (isset($decl['options']) && is_callable($decl['options'])) {
                $def['options'] = ($decl['options'])();
            }
            $defs[] = $def;
        }
        return $defs;
    }

    /**
     * Shape a paginator into the list envelope, with an optional summary
     * computed from the un-paginated clone of the query, and optional extra
     * meta (sortable keys — T6-21; filter definitions — T6-30).
     *
     * @param LengthAwarePaginator $paginator
     * @param callable|null $summary
     * @param array<string, mixed> $extraMeta
     * @return array{data: mixed, meta: array<string, mixed>, links: array<string, mixed>}
     */
    public static function envelope(LengthAwarePaginator $paginator, ?callable $summary = null, array $extraMeta = []): array
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
        // T6-21: the endpoint's accepted sort keys travel with every response
        $extraMeta['sortable'] ??= self::$lastSortable;
        // T6-30: the same declaration that filters the query drives the UI
        if (self::$lastFilterable !== []) {
            $extraMeta['filters'] ??= self::filterDefs();
        }
        foreach ($extraMeta as $key => $value) {
            $meta[$key] = $value;
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
