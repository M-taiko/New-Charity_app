<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\Treasury;
use App\Models\User;
use App\Support\EgyptianGovernorates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LookupController extends Controller
{
    /**
     * One cacheable call carrying all enumerations and reference data
     * the SPA needs to build forms and filters (plan 7.5).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $directionTree = ExpenseCategory::with([
            'children' => fn ($q) => $q->orderBy('order')->orderBy('name'),
            'children.children' => fn ($q) => $q->orderBy('order')->orderBy('name'),
            'children.children.children' => fn ($q) => $q->orderBy('order')->orderBy('name'),
            'children.items' => fn ($q) => $q->orderBy('order')->orderBy('name'),
            'children.children.items' => fn ($q) => $q->orderBy('order')->orderBy('name'),
            'children.children.children.items' => fn ($q) => $q->orderBy('order')->orderBy('name'),
        ])
            ->whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => [
                'roles' => \Spatie\Permission\Models\Role::orderBy('name')->get(['id', 'name']),
                'governorates' => EgyptianGovernorates::LIST,
                'statuses' => [
                    'custody' => ['pending', 'accepted', 'active', 'rejected', 'partially_returned', 'pending_return', 'closed', 'cancelled'],
                    'custody_transfer' => ['pending', 'approved', 'rejected'],
                    'return_request' => ['pending', 'approved', 'rejected'],
                    'review' => ['not_reviewed', 'reviewed'],
                    'social_case' => ['pending', 'approved', 'rejected'],
                    'purchase_request' => ['pending', 'approved', 'purchased', 'rejected'],
                    'maintenance_request' => ['pending', 'in_progress', 'resolved', 'rejected'],
                    'task' => ['pending', 'in_progress', 'completed', 'cancelled'],
                    'salary' => ['draft', 'approved', 'recorded'],
                    'edit_request' => ['pending', 'approved', 'rejected'],
                    'broadcast' => ['active', 'inactive'],
                    'transaction_type' => [
                        'custody_out', 'custody_return', 'donation', 'expense',
                        'custody_transfer_in', 'custody_transfer_out', 'transfer_in',
                        'transfer_out', 'recovery', 'custody_close', 'external_donation',
                    ],
                ],
                'treasuries' => Treasury::orderBy('name')->get(['id', 'name', 'balance'])->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'balance' => number_format((float) $t->balance, 2, '.', ''),
                ])->values(),
                'direction_tree' => $this->mapCategory($directionTree),
                // distinct values actually present in expenses.type (T31 expense filter)
                'expense_types' => Expense::distinct()->orderBy('type')->pluck('type')->values(),
                'users' => $this->visibleUsers($user)->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'roles' => $u->getRoleNames()->values(),
                ])->values(),
                'agents' => User::role('مندوب')->orderBy('name')->get(['id', 'name'])->values(),
                'researchers' => User::role('باحث اجتماعي')->orderBy('name')->get(['id', 'name'])->values(),
            ],
        ])->header('Cache-Control', 'private, max-age=60');
    }

    private function visibleUsers(User $viewer): iterable
    {
        $query = User::where('email', '!=', 'donia.a5ra2019@gmail.com')
            ->where('is_hidden', false)
            ->orderBy('name');

        return $query->get();
    }

    private function mapCategory(iterable $categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => $category->id,
                'name' => $category->name,
                'level' => $category->level ?? $category->depth ?? 1,
                'order' => (int) ($category->order ?? 0),
                'children' => $this->mapCategory($category->relationLoaded('children') ? $category->children : []),
                'items' => $category->relationLoaded('items')
                    ? $category->items->map(fn (ExpenseItem $item) => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'code' => $item->code,
                        'default_amount' => $item->default_amount !== null
                            ? number_format((float) $item->default_amount, 2, '.', '')
                            : null,
                        'is_active' => (bool) $item->is_active,
                    ])->values()->all()
                    : [],
            ];
        }

        return $result;
    }
}
