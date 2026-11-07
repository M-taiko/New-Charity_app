<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Services\ExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * T7-06: export is part of the list contract. Every list endpoint that
 * declares its sortable/filterable set gets GET {resource}/export?format=
 * xlsx|pdf|csv automatically — this helper replays the EXACT query the table
 * ran (same quick filters, filter[…] allowlist, search, sort), maps the rows
 * with the list's own row mapper, and streams through the one ExportService
 * (row cap + notice, audit log, filters/sort summary in the header).
 */
final class ListExport
{
    /**
     * Run the replayed list query and return the display rows (capped at
     * ExportService::MAX_ROWS + 1 so the service can show its cap notice).
     *
     * @template TModel
     * @param EloquentBuilder|QueryBuilder $query the same base query the list endpoint paginates
     * @param array<string, string> $sortable the list endpoint's sortable map
     * @param array<string, array{type:string, column?:string, options?:callable, lookup?:string, apply?:callable}> $filterable
     * @param array<string, callable> $filters the list endpoint's legacy quick params
     * @param callable|null $search the list endpoint's q handler
     * @param callable(TModel): array $row the list endpoint's row mapper — same shape as the JSON rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function rows(
        Request $request,
        EloquentBuilder|QueryBuilder $query,
        array $sortable = [],
        array $filterable = [],
        array $filters = [],
        ?callable $search = null,
        ?callable $row = null,
    ): Collection {
        foreach ($filters as $param => $applier) {
            if ($request->filled($param)) {
                $applier($query, $request->query($param));
            }
        }
        ApiList::applyDeclaredFilters($query, $request, $filterable);
        if ($search !== null && $request->filled('q')) {
            $search($query, (string) $request->query('q'));
        }
        ApiList::applySort($query, $request, $sortable);

        $models = $query->limit(ExportService::MAX_ROWS + 1)->get();

        return $row !== null ? $models->map($row) : $models->values();
    }

    /**
     * Stream the export. Authorization stays the caller's responsibility —
     * the export method must run the SAME checks as its list endpoint.
     *
     * @param Collection<int, array<string, mixed>> $rows
     * @param array<int, array{key: string, label: string, numeric?: bool, total?: bool}> $columns
     */
    public static function stream(Request $request, Collection $rows, array $columns, string $reportName): StreamedResponse
    {
        return app(ExportService::class)->export($request, $rows, $columns, $reportName);
    }
}
