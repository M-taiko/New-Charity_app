<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexSalaryHistoryRequest;
use App\Services\SalaryHistoryService;
use App\Support\Api\ApiList;
use App\Support\Api\ListExport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/v1/hr/salary-history — the salary record of every employee, and
 * its export. Authorisation and validation live in the Form Request, the
 * declaration of what may be filtered and sorted in the service.
 */
final class SalaryHistoryController extends Controller
{
    public function __invoke(IndexSalaryHistoryRequest $request, SalaryHistoryService $service): JsonResponse
    {
        return response()->json(ApiList::envelope($service->paginate($request)));
    }

    /** GET /api/v1/hr/salary-history/export?format=xlsx|pdf|csv */
    public function export(IndexSalaryHistoryRequest $request, SalaryHistoryService $service): StreamedResponse
    {
        return ListExport::stream($request, $service->exportRows($request), $service->exportColumns(), 'salary-history');
    }
}
