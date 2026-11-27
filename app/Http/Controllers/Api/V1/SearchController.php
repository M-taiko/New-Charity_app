<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Resources\Api\V1\SearchResource;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;

/**
 * N-01: GET /api/v1/search?q= — records for the command palette, grouped and
 * scoped. The request validates, the service knows the rules, the resource
 * shapes the answer: the controller only hands things over.
 */
final class SearchController extends Controller
{
    public function __construct(private readonly GlobalSearchService $search) {}

    public function __invoke(SearchRequest $request): JsonResponse
    {
        $term = $request->term();
        $result = $this->search->search($request->user(), $term);

        return (new SearchResource($request->user(), $result['groups'], $result['searched'], $term))
            ->toResponse($request)
            ->setStatusCode(200);
    }
}
