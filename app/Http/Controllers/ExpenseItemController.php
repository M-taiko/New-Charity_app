<?php

namespace App\Http\Controllers;

use App\Models\ExpenseItem;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ExpenseItemController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->authorize('manage_expense_items');
            return $next($request);
        });
    }

    public function index()
    {
        $categories = ExpenseCategory::active()->ordered()->get();
        return view('expense-items.index', compact('categories'));
    }

    public function data(Request $request)
    {
        $query = ExpenseItem::with('category')->orderBy('expense_category_id')->orderBy('order');

        if ($request->has('category_id') && $request->category_id) {
            $query->where('expense_category_id', $request->category_id);
        }

        return DataTables::of($query)
            ->addColumn('category_name', function ($item) {
                return $item->category->name;
            })
            ->addColumn('status', function ($item) {
                return $item->is_active
                    ? '<span class="badge bg-success">نشط</span>'
                    : '<span class="badge bg-secondary">غير نشط</span>';
            })
            ->addColumn('action', function ($item) {
                return '
                    <button class="btn btn-sm btn-primary" onclick="editItem(' . $item->id . ')">
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteItem(' . $item->id . ')">
                        <i class="fas fa-trash"></i> حذف
                    </button>
                ';
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function create()
    {
        $categories = ExpenseCategory::active()->ordered()->get();
        return view('expense-items.form', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:expense_items,code',
            'default_amount' => 'nullable|numeric|min:0',
            'order' => 'required|integer|min:1',
        ]);

        ExpenseItem::create($request->all());

        return redirect()->route('expense-items.index')->with('success', 'تم إضافة البند بنجاح');
    }

    public function edit(ExpenseItem $expenseItem)
    {
        $categories = ExpenseCategory::active()->ordered()->get();
        return view('expense-items.form', compact('expenseItem', 'categories'));
    }

    public function update(Request $request, ExpenseItem $expenseItem)
    {
        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:expense_items,code,' . $expenseItem->id,
            'default_amount' => 'nullable|numeric|min:0',
            'order' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $expenseItem->update($request->all());

        return redirect()->route('expense-items.index')->with('success', 'تم تحديث البند بنجاح');
    }

    public function destroy(ExpenseItem $expenseItem)
    {
        $expenseItem->delete();
        return redirect()->route('expense-items.index')->with('success', 'تم حذف البند بنجاح');
    }

    public function toggleStatus(ExpenseItem $expenseItem)
    {
        $expenseItem->update(['is_active' => !$expenseItem->is_active]);
        return back()->with('success', 'تم تحديث حالة البند بنجاح');
    }
}
