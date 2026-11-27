<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkCaseActiveRequest;
use App\Http\Requests\Api\V1\BulkExpenseReviewRequest;
use App\Http\Resources\Api\V1\BulkResultResource;
use App\Models\User;
use App\Services\BulkActionsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

/**
 * N-04: the bulk actions — one endpoint per action, each id judged on its
 * own by the service. The controller validates, hands over and shapes: a
 * user who may not do the action at all is answered in JSON, on this API.
 */
final class BulkActionController extends Controller
{
    public function __construct(private readonly BulkActionsService $bulk) {}

    /** POST /api/v1/expenses/bulk-review { action: review | unreview, ids: [] } */
    public function reviewExpenses(BulkExpenseReviewRequest $request): BulkResultResource|JsonResponse
    {
        $unreview = $request->unreview();
        $action = $unreview ? 'unreview' : 'review';

        try {
            $result = $this->bulk->reviewExpenses($this->user($request), $request->idList(), $unreview);
        } catch (AuthorizationException $e) {
            return $this->forbidden($e);
        }

        return $this->answer($result, $action, $unreview ? 'تم إلغاء مراجعة' : 'تمت مراجعة');
    }

    /** POST /api/v1/social-cases/bulk-active { active: true | false, ids: [] } */
    public function setCasesActive(BulkCaseActiveRequest $request): BulkResultResource|JsonResponse
    {
        $active = $request->makeActive();
        $action = $active ? 'activate' : 'deactivate';

        try {
            $result = $this->bulk->setCasesActive($this->user($request), $request->idList(), $active);
        } catch (AuthorizationException $e) {
            return $this->forbidden($e);
        }

        return $this->answer($result, $action, $active ? 'تم تنشيط' : 'تم إيقاف');
    }

    /**
     * The result as the API answers it: `{ data, message, level, meta }`. The
     * message counts what was done; the refusals are in `results`, and the
     * SPA's toast lists them.
     *
     * @param  array{results: list<array{id: int, status: string, code?: string, message?: string}>, done: int, refused: int, amount: string}  $result
     */
    private function answer(array $result, string $action, string $verb): BulkResultResource
    {
        return new BulkResultResource($result, $action, $verb . ' ' . $result['done']);
    }

    private function forbidden(AuthorizationException $e): JsonResponse
    {
        return response()->json([
            'data' => null,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : 'ليست لديك صلاحية لهذا الإجراء',
            'code' => 'bulk.forbidden',
            'level' => 'error',
        ], 403);
    }

    /** the signed-in user of an authenticated route, as the model the services know */
    private function user(BulkExpenseReviewRequest|BulkCaseActiveRequest $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
