<?php

namespace App\Http\Controllers;

use App\Models\ExpenseItem;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ExpenseItemController extends Controller
{
    public function index()
    {
        $this->authorize('manage_expense_items');
        // المستوى الأول فقط (جذور الشجرة)
        $roots = ExpenseCategory::with('children.children.items')
            ->roots()->active()->ordered()->get();
        return view('expense-items.index', compact('roots'));
    }

    public function data(Request $request)
    {
        $this->authorize('manage_expense_items');
        $query = ExpenseItem::with('category.parent.parent')->orderBy('expense_category_id')->orderBy('order');

        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->category_id);
        }

        return DataTables::of($query)
            ->addColumn('full_path', fn($item) => $item->category->full_path ?? $item->category->name)
            ->addColumn('status', fn($item) => $item->is_active
                ? '<span class="badge bg-success">نشط</span>'
                : '<span class="badge bg-secondary">غير نشط</span>')
            ->addColumn('action', fn($item) => view('expense-items.actions', compact('item'))->render())
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function create()
    {
        $this->authorize('manage_expense_items');
        $roots = ExpenseCategory::roots()->active()->ordered()->get();
        return view('expense-items.form', compact('roots'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage_expense_items');
        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'name'                => 'required|string|max:255',
            'code'                => 'required|string|max:50|unique:expense_items,code',
            'default_amount'      => 'nullable|numeric|min:0',
            'order'               => 'required|integer|min:1',
        ]);

        ExpenseItem::create($request->only(['expense_category_id', 'name', 'code', 'default_amount', 'order']));

        return redirect()->route('expense-items.index')->with('success', 'تم إضافة البند بنجاح');
    }

    public function edit(ExpenseItem $expenseItem)
    {
        $this->authorize('manage_expense_items');
        $roots = ExpenseCategory::roots()->active()->ordered()->get();
        return view('expense-items.form', compact('expenseItem', 'roots'));
    }

    public function update(Request $request, ExpenseItem $expenseItem)
    {
        $this->authorize('manage_expense_items');
        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'name'                => 'required|string|max:255',
            'code'                => 'required|string|max:50|unique:expense_items,code,' . $expenseItem->id,
            'default_amount'      => 'nullable|numeric|min:0',
            'order'               => 'required|integer|min:1',
            'is_active'           => 'boolean',
        ]);

        $expenseItem->update($request->only(['expense_category_id', 'name', 'code', 'default_amount', 'order', 'is_active']));

        return redirect()->route('expense-items.index')->with('success', 'تم تحديث البند بنجاح');
    }

    public function destroy(ExpenseItem $expenseItem)
    {
        $this->authorize('manage_expense_items');
        $expenseItem->delete();
        return redirect()->route('expense-items.index')->with('success', 'تم حذف البند بنجاح');
    }

    public function toggleStatus(ExpenseItem $expenseItem)
    {
        $this->authorize('manage_expense_items');
        $expenseItem->update(['is_active' => !$expenseItem->is_active]);
        return back()->with('success', 'تم تحديث حالة البند بنجاح');
    }

    // ──────────────────────────────────────────
    // Category Management
    // ──────────────────────────────────────────

    public function storeCategory(Request $request)
    {
        $this->authorize('manage_expense_items');
        $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'required|string|max:50|unique:expense_categories,code',
            'parent_id' => 'nullable|exists:expense_categories,id',
            'order'     => 'nullable|integer|min:1',
        ]);

        $level = 1;
        if ($request->parent_id) {
            $parent = ExpenseCategory::findOrFail($request->parent_id);
            $level  = $parent->level + 1;
        }

        ExpenseCategory::create([
            'parent_id'   => $request->parent_id,
            'level'       => $level,
            'name'        => $request->name,
            'code'        => $request->code,
            'description' => $request->description,
            'is_active'   => true,
            'order'       => $request->order ?? 1,
        ]);

        return back()->with('success', 'تم إضافة التصنيف بنجاح');
    }

    public function destroyCategory(ExpenseCategory $expenseCategory)
    {
        $this->authorize('manage_expense_items');
        $expenseCategory->delete();
        return back()->with('success', 'تم حذف التصنيف بنجاح');
    }

    // ──────────────────────────────────────────
    // API for Cascading Dropdowns
    // ──────────────────────────────────────────

    public function categoryRoots()
    {
        $roots = ExpenseCategory::roots()->active()->ordered()
            ->get(['id', 'name', 'code', 'level']);
        return response()->json($roots);
    }

    public function categoryChildren(ExpenseCategory $category)
    {
        $children = $category->children()->active()
            ->get(['id', 'name', 'code', 'level', 'parent_id']);
        return response()->json($children);
    }

    public function categoryItems(ExpenseCategory $category)
    {
        $items = $category->items()->where('is_active', true)->orderBy('order')
            ->get(['id', 'name', 'code', 'default_amount']);
        return response()->json($items);
    }

    /**
     * Returns ancestor chain for a category: [level1_id, level2_id, level3_id?]
     * Used by the edit form to reconstruct the cascading dropdown state.
     */
    public function categoryAncestors(ExpenseCategory $category)
    {
        $ancestors = [];
        $current = $category;

        // Walk up the parent chain
        while ($current->parent_id) {
            array_unshift($ancestors, $current->id);
            $current = $current->parent;
        }
        array_unshift($ancestors, $current->id); // add root

        return response()->json($ancestors);
    }
}
