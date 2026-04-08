<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Custody;
use App\Models\SocialCase;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\Treasury;
use App\Services\TreasuryService;
use App\Services\ActivityLogService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private TreasuryService $service) {}

    public function index()
    {
        $user = auth()->user();

        // المدير والمحاسب يرون كل المصروفات
        if ($user->can('view_all_expenses')) {
            return view('expenses.modern');
        }

        // المندوب يُوجَّه لصفحة مصروفاته الخاصة
        return redirect()->route('expenses.agent');
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
        $categoryRoots = ExpenseCategory::roots()->active()->ordered()->get();

        // Check if current user is accountant (محاسب) - can spend from treasury
        $canSpendFromTreasury = auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير');

        // Get treasury balance for display when spending from treasury
        $treasury = null;
        if ($canSpendFromTreasury) {
            $treasury = Treasury::first();
        }

        return view('expenses.modern-create', compact('custodies', 'cases', 'categoryRoots', 'canSpendFromTreasury', 'treasury'));
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

            $lineItems = $request->filled('line_items_data')
                ? json_decode($request->line_items_data, true)
                : null;

            $this->service->recordDirectExpenseFromTreasury(
                auth()->id(),
                $request->amount,
                $request->expense_category_id,
                $request->expense_item_id,
                $request->description,
                $request->location,
                $request->social_case_id,
                $attachmentPath,
                $request->expense_type,
                $lineItems
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

            $lineItems = $request->filled('line_items_data')
                ? json_decode($request->line_items_data, true)
                : null;

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
                $request->expense_type,
                $lineItems
            );
        }

        ActivityLogService::log('created', 'تم تسجيل مصروف جديد بمبلغ ' . number_format($request->amount, 2) . ' ج.م');

        return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف');
    }

    public function show(Expense $expense)
    {
        return view('expenses.modern-show', compact('expense'));
    }

    public function edit(Expense $expense)
    {
        $user = auth()->user();

        // فقط المحاسب والمدير يقدروا يعدلوا مباشرة
        if (!$user->hasRole('محاسب') && !$user->hasRole('مدير')) {
            abort(403, 'المندوبون يجب أن يستخدموا نظام طلبات التعديل');
        }

        // إذا كان المصروف معتمداً، فقط المدير يقدر يعدل
        if ($expense->isApproved() && !$user->hasRole('مدير')) {
            abort(403, 'المصروفات المعتمدة لا يمكن تعديلها إلا من قِبَل المدير');
        }

        $cases = SocialCase::where('status', 'approved')->get();
        $categoryRoots = ExpenseCategory::roots()->active()->ordered()->get();

        return view('expenses.modern-edit', compact('expense', 'cases', 'categoryRoots'));
    }

    public function update(Request $request, Expense $expense)
    {
        $user = auth()->user();

        // فقط المحاسب والمدير يقدروا يعدلوا مباشرة
        if (!$user->hasRole('محاسب') && !$user->hasRole('مدير')) {
            abort(403, 'غير مصرح لك بهذا الإجراء');
        }

        // إذا كان المصروف معتمداً، فقط المدير يقدر يعدل
        if ($expense->isApproved() && !$user->hasRole('مدير')) {
            abort(403, 'المصروفات المعتمدة لا يمكن تعديلها إلا من قِبَل المدير');
        }

        $category = ExpenseCategory::find($request->expense_category_id);
        $isOtherExpense = $category && $category->code === 'OTHER';

        $rules = [
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_type'        => 'required|in:social_case,general',
            'amount'              => 'required|numeric|min:0.01|max:1000000',
            'description'         => 'required|string|max:500',
            'location'            => 'nullable|string|max:255',
            'social_case_id'      => 'nullable|exists:social_cases,id',
            'expense_date'        => 'required|date',
            'attachment'          => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
        ];

        if ($request->expense_type === 'social_case') {
            $rules['social_case_id'] = 'required|exists:social_cases,id';
        }

        $rules['expense_item_id'] = $isOtherExpense
            ? 'nullable|exists:expense_items,id'
            : 'required|exists:expense_items,id';

        $request->validate($rules, [
            'attachment.max'   => 'حجم الملف يجب أن يكون أقل من 2 ميجابايت',
            'attachment.mimes' => 'الملفات المسموحة فقط: PDF, JPG, PNG, DOC, DOCX',
        ]);

        // تحديث custody.spent إذا تغير المبلغ
        $oldAmount = (float) $expense->amount;
        $newAmount = (float) $request->amount;

        if ($expense->custody_id && $oldAmount !== $newAmount) {
            $custody = Custody::findOrFail($expense->custody_id);
            $diff = $newAmount - $oldAmount;
            $custody->increment('spent', $diff);
        }

        // رفع المرفق الجديد إذا وُجد
        $attachmentPath = $expense->attachment;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('expense_attachments', 'public');
        }

        $expense->update([
            'expense_category_id' => $request->expense_category_id,
            'expense_item_id'     => $request->expense_item_id,
            'type'                => $request->expense_type,
            'amount'              => $newAmount,
            'description'         => $request->description,
            'location'            => $request->location,
            'social_case_id'      => $request->expense_type === 'social_case' ? $request->social_case_id : null,
            'expense_date'        => $request->expense_date,
            'attachment'          => $attachmentPath,
        ]);

        ActivityLogService::updated($expense, 'تم تعديل المصروف #' . $expense->id . ' (المبلغ: ' . number_format($newAmount, 2) . ' ج.م)');

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'تم تعديل المصروف بنجاح');
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

    public function tableData(Request $request)
    {
        $this->authorize('view_all_expenses');

        $query = Expense::with(['user', 'custody', 'socialCase', 'category']);

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        // Type filter
        if ($request->filled('type_filter')) {
            $query->where('type', $request->type_filter);
        }

        // Review status filter
        if ($request->filled('reviewed_filter')) {
            if ($request->reviewed_filter === 'reviewed') {
                $query->whereNotNull('reviewed_at');
            } else {
                $query->whereNull('reviewed_at');
            }
        }

        // User filter (name search)
        if ($request->filled('user_filter')) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', '%' . $request->user_filter . '%'));
        }

        return DataTables::of($query)
            ->addColumn('user_name', fn($row) => $row->user->name)
            ->addColumn('case_name', fn($row) => $row->socialCase->name ?? '-')
            ->addColumn('type_label', fn($row) => $row->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام')
            ->addColumn('category_name', fn($row) => $row->category->name ?? '-')
            ->addColumn('reviewed_label', fn($row) => $row->reviewed_at ? 'مراجع' : 'غير مراجع')
            ->filterColumn('user_name', fn($q, $k) => $q->whereHas('user', fn($q2) => $q2->where('name', 'like', "%$k%")))
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

    public function markReviewed(Expense $expense)
    {
        $user = auth()->user();
        if (!$user->hasRole('محاسب') && !$user->hasRole('مدير')) {
            abort(403);
        }

        $expense->update([
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        // إشعار المندوب
        \App\Services\NotificationService::notifyUser(
            $expense->user_id,
            'تمت مراجعة مصروفك',
            'قام ' . $user->name . ' بمراجعة المصروف رقم #' . $expense->id . ' وتم قفل التعديل',
            'info',
            $expense->id,
            'expense'
        );

        ActivityLogService::reviewed($expense, 'تمت مراجعة المصروف #' . $expense->id . ' بواسطة ' . $user->name);

        return back()->with('success', 'تمت مراجعة المصروف وتم قفل التعديل');
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
            $expenseId = $expense->id;
            $expenseAmount = $expense->amount;
            $expense->delete();
            ActivityLogService::deleted($expense, 'تم حذف المصروف #' . $expenseId . ' (المبلغ: ' . number_format($expenseAmount, 2) . ' ج.م)');
            return redirect()->route('expenses.index')
                ->with('success', 'تم حذف المصروف بنجاح');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'حدث خطأ أثناء حذف المصروف: ' . $e->getMessage());
        }
    }
}
