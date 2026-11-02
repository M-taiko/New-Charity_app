<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseEditRequest;
use App\Support\Api\ApiList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExpenseController extends Controller
{
    /**
     * GET /api/v1/expenses — all expenses with the full T31 filter set
     * (direction cascade, no-direction, custody incl. split rows, amount range)
     * and a filtered total in the summary.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view_all_expenses');

        $query = Expense::with(['user', 'custody', 'socialCase', 'reviewer', 'category.parent.parent', 'item']);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('reviewed')) {
            $request->reviewed === 'reviewed'
                ? $query->whereNotNull('reviewed_at')
                : $query->whereNull('reviewed_at');
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }
        if ($request->filled('q')) {
            $term = (string) $request->q;
            $query->where(fn ($w) => $w
                ->where('description', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")));
        }

        // T31: التوجيه — العقدة المختارة وكل ما تحتها، أو "بلا توجيه"
        if ($request->boolean('no_direction')) {
            $query->whereNull('expense_category_id')->whereNull('expense_item_id');
        } elseif ($request->filled('category_id') || $request->filled('item_id')) {
            $query->inDirection(
                $request->filled('category_id') ? (int) $request->category_id : null,
                $request->filled('item_id') ? (int) $request->item_id : null,
            );
        }

        // T31: العهدة — مباشرة أو عبر توزيع expense_custody (T27)
        if ($request->filled('custody_id')) {
            $custodyId = (int) $request->custody_id;
            $query->where(function ($q) use ($custodyId) {
                $q->where('custody_id', $custodyId)
                    ->orWhereHas('custodies', fn ($qc) => $qc->where('custodies.id', $custodyId));
            });
        }
        if ($request->filled('amount_from')) {
            $query->where('amount', '>=', round((float) $request->amount_from, 2));
        }
        if ($request->filled('amount_to')) {
            $query->where('amount', '<=', round((float) $request->amount_to, 2));
        }

        $filteredQuery = clone $query;

        $paginator = ApiList::paginate($query, $request, sortable: [
            'date' => 'created_at', 'amount' => 'amount', 'user' => 'user_id', 'type' => 'type',
            'direction' => 'expense_category_id', 'reviewed' => 'reviewed_at',
        ]);

        $user = $request->user();
        $isManager = $user->hasRole('مدير');
        $isAccountant = $user->hasRole('محاسب');
        $pendingEditIds = ExpenseEditRequest::where('status', 'pending')->distinct()->pluck('expense_id')->flip();

        $paginator->getCollection()->transform(fn (Expense $e) => $this->listRow($e, $isManager, $isAccountant, $pendingEditIds));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'filtered_total' => number_format((float) $filteredQuery->sum('amount'), 2, '.', ''),
            'count' => $filteredQuery->count(),
        ]));
    }

    /** GET /api/v1/my-expenses */
    public function mine(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            Expense::where('user_id', $request->user()->id)->with(['socialCase', 'category', 'item']),
            $request,
            sortable: ['date' => 'created_at', 'amount' => 'amount', 'type' => 'type', 'description' => 'description'],
            search: fn ($q, $term) => $q->where('description', 'like', "%{$term}%"),
            filters: ['type' => fn ($q, $v) => $q->where('type', $v)],
        );
        $paginator->getCollection()->transform(fn (Expense $e) => $this->listRow($e, false, false, null));

        $all = Expense::where('user_id', $request->user()->id);
        return response()->json(ApiList::envelope($paginator, fn () => [
            'total_amount' => number_format((float) (clone $all)->sum('amount'), 2, '.', ''),
            'count' => (clone $all)->count(),
            'general_count' => (clone $all)->where('type', 'general')->count(),
            'social_count' => (clone $all)->where('type', 'social_case')->count(),
        ]));
    }

    /** GET /api/v1/expenses/{id} */
    public function show(Request $request, Expense $expense): JsonResponse
    {
        // IDOR: expense details only for viewers-of-all or the owner
        abort_unless(
            $request->user()->can('view_all_expenses') || $expense->user_id === $request->user()->id,
            403,
            'غير مصرح لك بعرض هذا المصروف'
        );

        $expense->load(['user', 'custody', 'treasury', 'socialCase', 'reviewer', 'category.parent.parent', 'item', 'custodies']);

        $user = $request->user();
        $isManager = $user->hasRole('مدير');
        $isAccountant = $user->hasRole('محاسب');
        $pendingEditIds = ExpenseEditRequest::where('status', 'pending')->distinct()->pluck('expense_id')->flip();

        return response()->json([
            'data' => $this->listRow($expense, $isManager, $isAccountant, $pendingEditIds) + [
                'treasury' => $expense->treasury?->name,
                'location' => $expense->location,
                'notes' => $expense->notes,
                'source' => $expense->source,
                'line_items' => $expense->line_items,
                'attachment' => $expense->attachment,
                'expense_date' => $expense->expense_date?->toIso8601ZuluString(),
                'is_quick_expense' => (bool) $expense->is_quick_expense,
                'direction_ids' => [
                    'category_id' => $expense->expense_category_id,
                    'item_id' => $expense->expense_item_id,
                ],
                // T27: توزيع الصرف على العهدات
                'custody_split' => $expense->custodies->map(fn ($c) => [
                    'id' => $c->id,
                    'amount' => number_format((float) $c->pivot->amount, 2, '.', ''),
                ]),
                'edit_requests' => $expense->editRequests->map(fn ($r) => [
                    'id' => $r->id,
                    'status' => $r->status,
                    'requested_by' => $r->requester?->name,
                    'requested_at' => $r->requested_at?->toIso8601ZuluString(),
                ]),
            ],
        ]);
    }

    /** GET /api/v1/expense-edit-requests */
    public function editRequests(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            ExpenseEditRequest::with(['expense', 'requester', 'reviewer']),
            $request,
            sortable: ['date' => 'requested_at', 'amount' => 'amount', 'requested_at' => 'requested_at', 'requested_by' => 'requested_by', 'status' => 'status', 'expense_id' => 'expense_id', 'original_amount' => 'expense_id', 'requested_amount' => 'expense_id'],
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
        );
        $paginator->getCollection()->transform(fn ($r) => $this->editRequestRow($r));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'pending' => ExpenseEditRequest::where('status', 'pending')->count(),
            'approved' => ExpenseEditRequest::where('status', 'approved')->count(),
            'rejected' => ExpenseEditRequest::where('status', 'rejected')->count(),
        ]));
    }

    /** GET /api/v1/expense-edit-requests/{id} — before/after comparison (T16, T24) */
    public function editRequestShow(Request $request, ExpenseEditRequest $editRequest): JsonResponse
    {
        $editRequest->load(['expense.user', 'requester', 'reviewer']);

        return response()->json(['data' => $this->editRequestRow($editRequest) + [
            'original_data' => $editRequest->original_data,
            'requested_changes' => $editRequest->requested_changes,
        ]]);
    }

    private function editRequestRow(ExpenseEditRequest $r): array
    {
        return [
            'id' => $r->id,
            'expense_id' => $r->expense_id,
            'requested_by' => $r->requester?->name,
            'original_amount' => number_format((float) ($r->original_data['amount'] ?? 0), 2, '.', ''),
            'requested_amount' => number_format((float) ($r->requested_changes['amount'] ?? $r->original_data['amount'] ?? 0), 2, '.', ''),
            'status' => $r->status,
            'rejection_reason' => $r->rejection_reason,
            'requested_at' => $r->requested_at?->toIso8601ZuluString() ?? $r->created_at?->toIso8601ZuluString(),
            'reviewed_by' => $r->reviewer?->name,
            'reviewed_at' => $r->reviewed_at?->toIso8601ZuluString(),
        ];
    }

    private function listRow(Expense $e, bool $isManager, bool $isAccountant, ?\Illuminate\Support\Collection $pendingEditIds): array
    {
        return [
            'id' => $e->id,
            'user' => $e->user?->name,
            'custody_id' => $e->custody_id,
            'case' => $e->socialCase?->name,
            'type' => $e->type,
            'amount' => number_format((float) $e->amount, 2, '.', ''),
            'description' => $e->description,
            'created_at' => $e->created_at?->toIso8601ZuluString(),
            'expense_date' => $e->expense_date?->toIso8601ZuluString(),
            'direction' => $e->accounting_path,
            'has_direction' => $e->expense_category_id !== null || $e->expense_item_id !== null,
            'reviewed' => $e->reviewed_at !== null,
            'reviewed_by' => $e->reviewer?->name,
            'reviewed_at' => $e->reviewed_at?->toIso8601ZuluString(),
            'is_quick_expense' => (bool) $e->is_quick_expense,
            'items_count' => is_array($e->line_items) && ! isset($e->line_items['raw_text']) ? count($e->line_items) : 0,
            'has_attachment' => $e->attachment !== null && $e->attachment !== '',
            'can_review' => ($isAccountant || $isManager) && $e->reviewed_at === null,
            'can_unreview' => $isManager && $e->reviewed_at !== null,
            'can_edit' => ($isAccountant || ($isManager && $e->isApproved()))
                && ($pendingEditIds === null || ! $pendingEditIds->has($e->id)),
        ];
    }
}
