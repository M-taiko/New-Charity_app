<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Support\Api\ApiList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read endpoints for the four-level accounting direction tree and its items
 * (T30, T31, T33). Writes go through the existing ExpenseItemController
 * actions under /api/v1 — same rules, JSON transport.
 */
final class ExpenseItemController extends Controller
{
    /** GET /api/v1/expense-items — management list (flat, with hierarchy path) */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage_expense_items');

        $categories = ExpenseCategory::with('parent.parent')->ordered()->get();
        $items = ExpenseItem::with('category.parent.parent')->ordered()->get();

        $categoryRows = $categories->map(fn ($c) => [
            'id' => $c->id,
            'kind' => 'category',
            'name' => $c->name,
            'code' => $c->code,
            'level' => (int) $c->level,
            'parent_id' => $c->parent_id,
            'is_active' => (bool) $c->is_active,
            'order' => (int) $c->order,
            'path' => $c->full_path,
            'children_count' => $categories->where('parent_id', $c->id)->count(),
            'items_count' => $items->where('expense_category_id', $c->id)->count(),
        ]);

        $itemRows = $items->map(fn ($i) => [
            'id' => $i->id,
            'kind' => 'item',
            'name' => $i->name,
            'code' => $i->code,
            'level' => 4,
            'parent_id' => $i->expense_category_id,
            'is_active' => (bool) $i->is_active,
            'order' => (int) $i->order,
            'default_amount' => $i->default_amount !== null ? number_format((float) $i->default_amount, 2, '.', '') : null,
            'path' => $i->category?->full_path ? $i->category->full_path . ' ‹ ' . $i->name : $i->name,
        ]);

        return response()->json([
            'data' => [
                'categories' => $categoryRows,
                'items' => $itemRows,
                'stats' => [
                    'roots' => $categories->whereNull('parent_id')->count(),
                    'total_categories' => $categories->count(),
                    'total_items' => $items->count(),
                    'inactive' => $categories->where('is_active', false)->count() + $items->where('is_active', false)->count(),
                ],
            ],
        ]);
    }

}
