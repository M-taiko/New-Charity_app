<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Custody;
use App\Models\SocialCase;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\Treasury;
use App\Services\TreasuryService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private TreasuryService $service) {}

    public function index()
    {
        // المندوب يُوجَّه لصفحة مصروفاته الخاصة
        if (auth()->user()->hasRole('مندوب')) {
            return redirect()->route('expenses.agent');
        }

        // فقط المحاسب والمدير يرون جدول جميع المصروفات
        $this->authorize('spend_money');
        return view('expenses.modern');
    }

    public function create()
    {
        $this->authorize('spend_money');

        // Get custodies for current user only (agent_id) that are active/accepted with available balance
        $custodies = Custody::where('agent_id', auth()->id())
            ->whereIn('status', ['accepted', 'active'])
            ->get()
            ->filter(function($custody) {
                return $custody->getRemainingBalance() > 0;
            });

        $cases = SocialCase::where('status', 'approved')->get();
        $categories = ExpenseCategory::active()->with('items')->ordered()->get();

        // Check if current user is accountant (محاسب) - can spend from treasury
        $canSpendFromTreasury = auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير');

        // Get treasury balance for display when spending from treasury
        $treasury = null;
        if ($canSpendFromTreasury) {
            $treasury = Treasury::first();
        }

        return view('expenses.modern-create', compact('custodies', 'cases', 'categories', 'canSpendFromTreasury', 'treasury'));
    }

    public function store(Request $request)
    {
        $this->authorize('spend_money');

        // Determine source (default to custody)
        $source = $request->input('source', 'custody');

        if ($source === 'treasury') {
            // Direct treasury spending (for accountants)
            $this->authorize('direct_spend_from_treasury');

            // Get treasury balance
            $treasury = Treasury::first();
            $treasuryBalance = $treasury ? $treasury->balance : 0;

            // Validate category to determine if item is required
            $category = ExpenseCategory::find($request->expense_category_id);
            $isOtherExpense = $category && $category->code === 'OTHER';

            $rules = [
                'amount' => 'required|numeric|min:0.01|max:' . min($treasuryBalance, 1000000),
                'expense_category_id' => 'required|exists:expense_categories,id',
                'expense_type' => 'required|in:social_case,general',
                'description' => 'required|string|max:500',
                'location' => 'nullable|string',
                'social_case_id' => 'nullable|exists:social_cases,id',
                'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
            ];

            // If expense type is social_case, social_case_id is required
            if ($request->expense_type === 'social_case') {
                $rules['social_case_id'] = 'required|exists:social_cases,id';
            }

            // Item is required only for non-"OTHER" categories
            if (!$isOtherExpense) {
                $rules['expense_item_id'] = 'required|exists:expense_items,id';
            } else {
                $rules['expense_item_id'] = 'nullable|exists:expense_items,id';
            }

            $request->validate($rules, [
                'amount.max' => 'المبلغ المدخل يتجاوز رصيد الخزينة. الحد الأقصى: ' . number_format($treasuryBalance, 2) . ' ج.م',
                'attachment.max' => 'حجم الملف يجب أن يكون أقل من 2 ميجابايت',
                'attachment.mimes' => 'الملفات المسموحة فقط: PDF, JPG, PNG, DOC, DOCX',
            ]);

            // Handle file upload
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('expense_attachments', 'public');
            }

            $this->service->recordDirectExpenseFromTreasury(
                auth()->id(),
                $request->amount,
                $request->expense_category_id,
                $request->expense_item_id,
                $request->description,
                $request->location,
                $request->social_case_id,
                $attachmentPath,
                $request->expense_type
            );
        } else {
            // Custody spending - can use multiple custodies automatically

            // Get all active custodies for the user
            $availableCustodies = Custody::where('agent_id', auth()->id())
                ->whereIn('status', ['accepted', 'active'])
                ->get()
                ->filter(function($custody) {
                    return $custody->getRemainingBalance() > 0;
                });

            // Calculate total available balance
            $totalAvailableBalance = $availableCustodies->sum(function($custody) {
                return $custody->getRemainingBalance();
            });

            // Validate category to determine if item is required
            $category = ExpenseCategory::find($request->expense_category_id);
            $isOtherExpense = $category && $category->code === 'OTHER';

            $rules = [
                'custody_id' => 'nullable|exists:custodies,id', // Optional, for backward compatibility
                'amount' => 'required|numeric|min:0.01|max:' . min($totalAvailableBalance, 1000000),
                'expense_category_id' => 'required|exists:expense_categories,id',
                'expense_type' => 'required|in:social_case,general',
                'description' => 'required|string|max:500',
                'location' => 'nullable|string',
                'social_case_id' => 'nullable|exists:social_cases,id',
                'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
            ];

            // If expense type is social_case, social_case_id is required
            if ($request->expense_type === 'social_case') {
                $rules['social_case_id'] = 'required|exists:social_cases,id';
            }

            // Item is required only for non-"OTHER" categories
            if (!$isOtherExpense) {
                $rules['expense_item_id'] = 'required|exists:expense_items,id';
            } else {
                $rules['expense_item_id'] = 'nullable|exists:expense_items,id';
            }

            $request->validate($rules, [
                'amount.max' => 'المبلغ المدخل يتجاوز الرصيد المتاح في جميع عهدك. الحد الأقصى: ' . number_format($totalAvailableBalance, 2) . ' ج.م',
                'attachment.max' => 'حجم الملف يجب أن يكون أقل من 2 ميجابايت',
                'attachment.mimes' => 'الملفات المسموحة فقط: PDF, JPG, PNG, DOC, DOCX',
            ]);

            // Handle file upload
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('expense_attachments', 'public');
            }

            // Use first custody ID for backward compatibility, or first available
            $custodyId = $request->custody_id ?? $availableCustodies->first()->id;

            $this->service->recordExpenseWithItems(
                $custodyId,
                auth()->id(),
                $request->amount,
                $request->expense_category_id,
                $request->expense_item_id,
                $request->description,
                $request->location,
                $request->social_case_id,
                $attachmentPath,
                $request->expense_type
            );
        }

        return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف');
    }

    public function show(Expense $expense)
    {
        return view('expenses.modern-show', compact('expense'));
    }

    public function agentExpenses()
    {
        $user = auth()->user();

        // Check if user is agent (مندوب)
        if (!$user->hasRole('مندوب')) {
            abort(403, 'Unauthorized');
        }

        // Get agent's custodies
        $custodies = Custody::where('agent_id', $user->id)->pluck('id');

        // Get all expenses for agent's custodies
        $expenses = Expense::whereIn('custody_id', $custodies)->get();

        $totalExpenses = $expenses->sum('amount');
        $expenseCount = $expenses->count();
        $generalExpenseCount = $expenses->where('type', 'general')->count();
        $socialCaseExpenseCount = $expenses->where('type', 'social_case')->count();

        return view('expenses.agent-modern', compact('totalExpenses', 'expenseCount', 'generalExpenseCount', 'socialCaseExpenseCount'));
    }

    public function tableData()
    {
        $this->authorize('spend_money');

        $user = auth()->user();

        // Only managers and accountants can see all expenses
        // Agents should use their own page at /my-expenses
        if ($user->hasRole('مندوب')) {
            // Redirect agents to their own expenses page
            abort(403, 'يرجى استخدام صفحة مصروفاتي لعرض مصروفاتك');
        }

        // Get all expenses for managers and accountants
        $expenses = Expense::with(['user', 'custody', 'socialCase'])->get();

        return DataTables::of($expenses)
            ->addColumn('user_name', fn($row) => $row->user->name)
            ->addColumn('case_name', fn($row) => $row->socialCase->name ?? '-')
            ->addColumn('type_label', fn($row) => $row->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام')
            ->toJson();
    }

    public function agentExpensesData()
    {
        $user = auth()->user();

        // Check if user is agent (مندوب)
        if (!$user->hasRole('مندوب')) {
            abort(403, 'Unauthorized');
        }

        // Get agent's custodies
        $custodies = Custody::where('agent_id', $user->id)->pluck('id');

        // Get expenses for agent's custodies
        $expenses = Expense::with(['user', 'custody', 'socialCase'])
            ->whereIn('custody_id', $custodies)
            ->get();

        return DataTables::of($expenses)
            ->addColumn('case_name', fn($row) => $row->socialCase->name ?? '-')
            ->addColumn('type_label', fn($row) => $row->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام')
            ->toJson();
    }

    public function downloadAttachment(Expense $expense)
    {
        $this->authorize('spend_money');

        if (!$expense->attachment) {
            abort(404, 'لا يوجد مرفق لهذا المصروف');
        }

        $filePath = storage_path('app/public/' . $expense->attachment);

        if (!file_exists($filePath)) {
            abort(404, 'الملف غير موجود');
        }

        return response()->download($filePath);
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('spend_money');

        // Check authorization: only the creator, accountants, and managers can delete
        $user = auth()->user();
        $isCreator = $expense->user_id === $user->id;
        $isAccountantOrManager = $user->hasRole('محاسب') || $user->hasRole('مدير');

        if (!$isCreator && !$isAccountantOrManager) {
            abort(403, 'غير مصرح لك بحذف هذا المصروف');
        }

        try {
            $expense->delete();
            return redirect()->route('expenses.index')
                ->with('success', 'تم حذف المصروف بنجاح');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء حذف المصروف: ' . $e->getMessage());
        }
    }
}
