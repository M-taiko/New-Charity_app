<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Custody;
use App\Models\SocialCase;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use App\Support\LineItemsSanitizer;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Get custodies based on user role
        $user = auth()->user();
        if ($user->hasRole('مندوب')) {
            // Agents see only their own custodies
            $custodies = Custody::where('agent_id', $user->id)
                ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
                ->get();
        } else {
            // Managers and accountants see ALL custodies that are active
            $custodies = Custody::whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
                ->get();
        }

        $cases = SocialCase::where('status', 'approved')->get();
        $categoryRoots = ExpenseCategory::roots()->active()->ordered()->get();

        // Check if current user is accountant (محاسب) - can spend from treasury
        $canSpendFromTreasury = $user->hasRole('محاسب') || $user->hasRole('مدير');

        // Get treasuries for display when spending from treasury
        $treasuries = [];
        if ($canSpendFromTreasury) {
            $treasuries = Treasury::all();
        }

        return view('expenses.modern-create', compact('custodies', 'cases', 'categoryRoots', 'canSpendFromTreasury', 'treasuries'));
    }

    public function store(Request $request)
    {
        $this->authorize('spend_money');

        try {
            // Determine source (default to custody)
            $source = $request->input('source', 'custody');

            if ($source === 'treasury') {
                // Direct treasury spending (for accountants)
                $this->authorize('direct_spend_from_treasury');

                // Get selected treasury
                $treasuryId = $request->input('treasury_id');
                if (!$treasuryId) {
                    return back()->withInput()->with('error', 'يجب اختيار خزينة');
                }

                $treasury = Treasury::findOrFail($treasuryId);
                $treasuryBalance = $treasury->balance;

                $rules = [
                    'amount' => 'required|numeric|min:0.01|max:' . min($treasuryBalance, 1000000),
                    'expense_category_id' => 'required|exists:expense_categories,id',
                    'expense_type' => 'required|in:social_case,general',
                    'description' => 'required|string|max:500',
                    'location' => 'nullable|string',
                    'social_case_id' => 'nullable|exists:social_cases,id',
                    'expense_item_id' => 'nullable|exists:expense_items,id',
                    'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
                ];

                // If expense type is social_case, social_case_id is required
                if ($request->expense_type === 'social_case') {
                    $rules['social_case_id'] = 'required|exists:social_cases,id';
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

                $lineItems = LineItemsSanitizer::fromRequest($request);

            $expense = $this->service->recordDirectExpenseFromTreasury(
                $treasuryId,
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

            ActivityLogService::created($expense, 'تم تسجيل مصروف جديد بمبلغ ' . number_format($request->amount, 2) . ' ج.م');

            // Send notification to managers and accountants for review
            $user = auth()->user();
            $notificationMessage = "المستخدم: {$user->name} - سجل مصروفاً بمبلغ " . number_format($request->amount, 2) . " ج.م من الخزينة - الوصف: {$request->description}";
            NotificationService::notifyByRole('مدير', 'مصروف جديد للمراجعة', $notificationMessage, 'warning', $expense->id, 'expense');
            NotificationService::notifyByRole('محاسب', 'مصروف جديد للمراجعة', $notificationMessage, 'warning', $expense->id, 'expense');
            } else {
                // Custody spending - the custody must be selected explicitly

                $custody = Custody::findOrFail($request->input('custody_id'));

                // Agents can only spend from their own custodies
                if (auth()->user()->hasRole('مندوب') && $custody->agent_id !== auth()->id()) {
                    return back()->withInput()->with('error', 'غير مصرح لك بالصرف من هذه العهدة');
                }
                // Managers and accountants can spend from any custody

                $maxAmount = $custody->getRemainingBalance();

                $rules = [
                    'custody_id' => 'required|exists:custodies,id',
                    'amount' => 'required|numeric|min:0.01|max:' . $maxAmount,
                    'expense_category_id' => 'required|exists:expense_categories,id',
                    'expense_type' => 'required|in:social_case,general',
                    'description' => 'required|string|max:500',
                    'location' => 'nullable|string',
                    'social_case_id' => 'nullable|exists:social_cases,id',
                    'expense_item_id' => 'nullable|exists:expense_items,id',
                    'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
                ];

                // If expense type is social_case, social_case_id is required
                if ($request->expense_type === 'social_case') {
                    $rules['social_case_id'] = 'required|exists:social_cases,id';
                }

                $request->validate($rules, [
                    'amount.max' => 'المبلغ المدخل يتجاوز الرصيد المتاح. الحد الأقصى: ' . number_format($maxAmount, 2) . ' ج.م',
                    'attachment.max' => 'حجم الملف يجب أن يكون أقل من 2 ميجابايت',
                    'attachment.mimes' => 'الملفات المسموحة فقط: PDF, JPG, PNG, DOC, DOCX',
                ]);

                // Handle file upload
                $attachmentPath = null;
                if ($request->hasFile('attachment')) {
                    $attachmentPath = $request->file('attachment')->store('expense_attachments', 'public');
                }

                $lineItems = LineItemsSanitizer::fromRequest($request);

                $expense = $this->service->recordExpenseWithItems(
                    $request->custody_id,
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

                ActivityLogService::created($expense, 'تم تسجيل مصروف جديد بمبلغ ' . number_format($request->amount, 2) . ' ج.م');

                // Send notification to managers and accountants for review
                $user = auth()->user();
                $notificationMessage = "المستخدم: {$user->name} - سجل مصروفاً بمبلغ " . number_format($request->amount, 2) . " ج.م - الوصف: {$request->description}";
                NotificationService::notifyByRole('مدير', 'مصروف جديد للمراجعة', $notificationMessage, 'warning', $expense->id, 'Expense');
                NotificationService::notifyByRole('محاسب', 'مصروف جديد للمراجعة', $notificationMessage, 'warning', $expense->id, 'Expense');
            }

            return redirect()->route('expenses.agent')->with('success', 'تم تسجيل المصروف');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ أثناء تسجيل المصروف: ' . $e->getMessage());
        }
    }

    public function show(Expense $expense)
    {
        $expense->load(['user', 'custody', 'socialCase', 'category.parent.parent', 'item.category.parent.parent']);
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
        try {
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

            // البند إلزامي فقط إذا كانت الفئة المختارة تحتوي على بنود نشطة (و ليست OTHER)
            $categoryHasItems = $category
                && \App\Models\ExpenseItem::active()->where('expense_category_id', $category->id)->exists();

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

            $rules['expense_item_id'] = ($isOtherExpense || !$categoryHasItems)
                ? 'nullable'
                : 'required';

            $request->validate($rules, [
                'expense_item_id.required' => 'يجب اختيار التوجيه النهائي (المستوى الرابع) لأن هذه الفئة تحتوي على بنود',
                'expense_category_id.required' => 'يجب اختيار فئة المصروف',
                'expense_category_id.exists' => 'الفئة المختارة غير موجودة',
                'attachment.max'   => 'حجم الملف يجب أن يكون أقل من 2 ميجابايت',
                'attachment.mimes' => 'الملفات المسموحة فقط: PDF, JPG, PNG, DOC, DOCX',
            ]);

            // عند إرسال بند: التحقق أنه ينتمي للفئة المختارة
            $itemId = $request->filled('expense_item_id') ? (int) $request->expense_item_id : null;
            if ($itemId !== null) {
                $itemBelongsToCategory = \App\Models\ExpenseItem::where('id', $itemId)
                    ->where('expense_category_id', $request->expense_category_id)
                    ->exists();
                if (!$itemBelongsToCategory) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'expense_item_id' => 'البند المختار لا ينتمي إلى الفئة المختارة',
                    ]);
                }
            }

            // مقارنة المبالغ بعد التقريب لخانتين عشريتين (لا مقارنة floats مباشرة)
            $oldAmount = round((float) $expense->amount, 2);
            $newAmount = round((float) $request->amount, 2);
            $amountChanged = $oldAmount !== $newAmount;

            // عدد صفوف pivot للمصروف (التوزيع على العهد)
            $pivotCustodies = $expense->custodies()->get();

            // (D2) مصروف موزع على أكثر من عهدة + تغيير المبلغ = رفض
            if ($amountChanged && $pivotCustodies->count() > 1) {
                throw new \Exception('لا يمكن تعديل مبلغ مصروف موزّع على أكثر من عهدة. يمكن تعديل باقي الحقول فقط دون تغيير المبلغ.');
            }

            // العهدة المتأثرة فعلياً: صف pivot واحد إن وُجد، وإلا custody_id (مصروفات سريعة وغيرها)
            $affectedCustodyId = $pivotCustodies->count() === 1
                ? $pivotCustodies->first()->id
                : $expense->custody_id;

            // رفع المرفق الجديد إذا وُجد (خارج المعاملة)
            $attachmentPath = $expense->attachment;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('expense_attachments', 'public');
            }

            DB::transaction(function () use ($expense, $request, $itemId, $oldAmount, $newAmount, $amountChanged, $affectedCustodyId, $pivotCustodies, $attachmentPath) {
                if ($affectedCustodyId && $amountChanged) {
                    $diff = round($newAmount - $oldAmount, 2);

                    // قفل العهدة المتأثرة داخل المعاملة
                    $custody = Custody::where('id', $affectedCustodyId)->lockForUpdate()->first();
                    if (!$custody) {
                        throw new \Exception('العهدة المرتبطة بالمصروف غير موجودة');
                    }

                    if ($diff > 0) {
                        // حارس صريح لحالة العهدة: لا زيادة على عهدة مرفوضة/ملغاة/معلقة
                        if (!in_array($custody->status, ['accepted', 'active', 'partially_returned'])) {
                            throw new \Exception('لا يمكن زيادة مبلغ مصروف مرتبط بعهدة في حالة "' . $custody->status . '" (عهدة #' . $custody->id . ')');
                        }

                        $remaining = round((float) $custody->getRemainingBalance(), 2);
                        if ($diff > $remaining) {
                            throw new \Exception('الزيادة المطلوبة في المبلغ (' . number_format($diff, 2) . ' ج.م) تتجاوز الرصيد المتبقي للعهدة #' . $custody->id . ' (' . number_format($remaining, 2) . ' ج.م)');
                        }
                    }

                    // تطبيق الفرق على spent
                    $custody->increment('spent', $diff);

                    // تحديث مبلغ صف pivot إن وُجد
                    if ($pivotCustodies->count() === 1) {
                        $expense->custodies()->updateExistingPivot($custody->id, ['amount' => $newAmount]);
                    }

                    // تحديث حركة الخزينة المرتبطة إن وُجدت (المصروفات الجديدة بعد T13)
                    $transaction = TreasuryTransaction::where('expense_id', $expense->id)->first();
                    if ($transaction) {
                        $transaction->update(['amount' => $newAmount]);
                    }

                    // إغلاق العهدة إذا وصل رصيدها للصفر (لا يتم فتح عهدة مغلقة تلقائياً)
                    if ($custody->fresh()->getRemainingBalance() <= 0 && $custody->status !== 'closed') {
                        $custody->update(['status' => 'closed']);
                        TreasuryTransaction::create([
                            'treasury_id' => $custody->treasury_id,
                            'type' => 'custody_close',
                            'amount' => 0,
                            'description' => "إقفال عهدة #$custody->id للمندوب {$custody->agent->name} (رصيد صفر)",
                            'user_id' => auth()->id(),
                            'custody_id' => $custody->id,
                            'transaction_date' => now(),
                        ]);
                    }
                }

                $expense->update([
                    'expense_category_id' => $request->expense_category_id,
                    'expense_item_id'     => $itemId,
                    'line_items'          => $request->has('line_items')
                        ? LineItemsSanitizer::fromRequest($request)
                        : $expense->line_items,
                    'type'                => $request->expense_type,
                    'amount'              => $newAmount,
                    'description'         => $request->description,
                    'location'            => $request->location,
                    'social_case_id'      => $request->expense_type === 'social_case' ? $request->social_case_id : null,
                    'expense_date'        => $request->expense_date,
                    'attachment'          => $attachmentPath,
                    'is_quick_expense'    => false, // Mark as no longer a quick expense after editing
                    'approval_status'     => 'pending_edit', // Reset to pending edit
                ]);
            });

            ActivityLogService::updated($expense, 'تم تعديل المصروف #' . $expense->id . ' (المبلغ: ' . number_format($newAmount, 2) . ' ج.م)');

            return redirect()->route('expenses.show', $expense)
                ->with('success', 'تم تعديل المصروف بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ أثناء تعديل المصروف: ' . $e->getMessage());
        }
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

        // Get all expenses for agent's custodies OR expenses created by this agent
        $expenses = Expense::where(function($q) use ($custodies, $user) {
            $q->whereIn('custody_id', $custodies)
              ->orWhere('user_id', $user->id);  // Include expenses created by this user
        })->get();

        $totalExpenses = $expenses->sum('amount');
        $expenseCount = $expenses->count();
        $generalExpenseCount = $expenses->where('type', 'general')->count();
        $socialCaseExpenseCount = $expenses->where('type', 'social_case')->count();

        return view('expenses.agent-modern', compact('totalExpenses', 'expenseCount', 'generalExpenseCount', 'socialCaseExpenseCount'));
    }

    public function tableData(Request $request)
    {
        $this->authorize('view_all_expenses');

        $query = Expense::with(['user', 'custody', 'socialCase', 'reviewer', 'category.parent.parent', 'item.category.parent.parent']);

        // Date range filter - use created_at to match reports page
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
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

        // طلبات التعديل المعلقة باستعلام واحد مجمع (لا استعلام لكل صف)
        $user = auth()->user();
        $isAccountant = $user->hasRole('محاسب');
        $isManager = $user->hasRole('مدير');
        $pendingEditIds = \App\Models\ExpenseEditRequest::where('status', 'pending')
            ->distinct()->pluck('expense_id')->flip();

        return DataTables::of($query)
            ->addColumn('user_name', fn($row) => $row->user->name)
            ->addColumn('case_name', fn($row) => $row->socialCase->name ?? '-')
            ->addColumn('type_label', fn($row) => $row->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام')
            ->addColumn('category_name', fn($row) => $row->category->name ?? '-')
            ->addColumn('expense_datetime', fn($row) => $row->expense_date ? $row->expense_date->toIso8601String() : '-')
            ->addColumn('item_direction', fn($row) => $row->accounting_path ?? 'غير محدد')
            ->addColumn('is_quick_expense', fn($row) => $row->is_quick_expense ?? false)
            ->addColumn('reviewed_label', fn($row) => $row->reviewed_at ? 'مراجع' : 'غير مراجع')
            ->addColumn('can_review', fn($row) => ($isAccountant || $isManager) && !$row->reviewed_at)
            ->addColumn('can_edit', fn($row) => ($isAccountant || ($isManager && $row->isApproved())) && !$pendingEditIds->has($row->id))
            ->addColumn('can_unreview', fn($row) => $isManager && $row->reviewed_at)
            ->addColumn('edit_url', fn($row) => route('expenses.edit', $row))
            ->addColumn('show_url', fn($row) => route('expenses.show', $row))
            ->addColumn('reviewer_name', fn($row) => $row->reviewer?->name)
            ->addColumn('reviewed_at_formatted', fn($row) => $row->reviewed_at?->toIso8601String())
            ->addColumn('has_direction', fn($row) => $row->expense_category_id !== null || $row->expense_item_id !== null)
            ->addColumn('items_count', fn($row) => is_array($row->line_items) && !isset($row->line_items['raw_text']) ? count($row->line_items) : 0)
            ->addColumn('first_item', fn($row) => is_array($row->line_items) && !isset($row->line_items['raw_text']) && isset($row->line_items[0]['description']) ? $row->line_items[0]['description'] : null)
            // هذه الأعمدة تُهرَّب من جهة العميل في دوال render (esc) — تُرسل خام لتجنب الإescaping المزدوج
            ->rawColumns(['item_direction', 'first_item', 'reviewer_name', 'reviewed_at_formatted'])
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

        // Get expenses for agent's custodies OR direct expenses by this user
        // Hide original quick expenses (those that are still marked as quick_expense and lack proper category/item)
        $expenses = Expense::with(['user', 'custody', 'socialCase', 'category.parent.parent', 'item.category.parent.parent'])
            ->where(function($q) use ($custodies, $user) {
                $q->whereIn('custody_id', $custodies)
                  ->orWhere(function($q2) use ($user) {
                      $q2->whereNull('custody_id')->where('user_id', $user->id);
                  });
            })
            // Show all non-quick expenses and all quick expenses (regardless of edit status)
            ->orderBy('created_at', 'desc')
            ->get();

        return DataTables::of($expenses)
            ->editColumn('expense_date', fn($row) => $row->expense_date?->toIso8601String() ?? null)
            ->addColumn('type_label', function($row) {
                if ($row->is_quick_expense) {
                    return 'مصروف سريع';
                } elseif ($row->social_case_id) {
                    return 'حالة اجتماعية';
                } else {
                    return 'مصروف عام';
                }
            })
            ->addColumn('category_path', fn($row) => $row->accounting_path ?? 'غير محدد')
            ->addColumn('case_name', fn($row) => $row->socialCase->name ?? '-')
            ->addColumn('expense_datetime', fn($row) => $row->expense_date ? $row->expense_date->toIso8601String() : '-')
            ->addColumn('is_quick_expense', fn($row) => $row->is_quick_expense ? 1 : 0)
            ->rawColumns(['type_label', 'category_path'])
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

    public function markReviewed(Request $request, Expense $expense)
    {
        $user = auth()->user();
        if (!$user->hasRole('محاسب') && !$user->hasRole('مدير')) {
            abort(403);
        }

        $isQuickExpense = $expense->is_quick_expense;

        $expense->update([
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'is_quick_expense' => false, // Convert quick expense to regular
        ]);

        // إشعار المندوب
        $notificationMessage = $isQuickExpense
            ? 'قام ' . $user->name . ' بمراجعة المصروف السريع رقم #' . $expense->id . ' وتحويله إلى مصروف عادي وتم قفل التعديل'
            : 'قام ' . $user->name . ' بمراجعة المصروف رقم #' . $expense->id . ' وتم قفل التعديل';

        \App\Services\NotificationService::notifyUser(
            $expense->user_id,
            'تمت مراجعة مصروفك',
            $notificationMessage,
            'info',
            $expense->id,
            'expense'
        );

        $logMessage = $isQuickExpense
            ? 'تمت مراجعة المصروف السريع #' . $expense->id . ' وتحويله إلى مصروف عادي بواسطة ' . $user->name
            : 'تمت مراجعة المصروف #' . $expense->id . ' بواسطة ' . $user->name;

        ActivityLogService::reviewed($expense, $logMessage);

        $message = $isQuickExpense
            ? 'تمت مراجعة المصروف وتحويله من مصروف سريع إلى مصروف عادي وتم قفل التعديل'
            : 'تمت مراجعة المصروف وتم قفل التعديل';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * إلغاء المراجعة - للمدير فقط (D5-a)
     * يفتح التعديل مرة أخرى دون إرجاع المصروف إلى حالة "سريع"
     */
    public function unreview(Request $request, Expense $expense)
    {
        $user = auth()->user();
        abort_unless($user->hasRole('مدير'), 403, 'فقط المدير يمكنه إلغاء المراجعة');

        if (!$expense->isReviewed()) {
            $message = 'هذا المصروف غير مراجع أصلاً';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $expense->update([
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        \App\Services\NotificationService::notifyUser(
            $expense->user_id,
            'تم فتح التعديل على مصروف',
            'قام المدير ' . $user->name . ' بإلغاء مراجعة المصروف رقم #' . $expense->id . ' - أصبح التعديل متاحاً مرة أخرى',
            'warning',
            $expense->id,
            'expense'
        );

        ActivityLogService::updated($expense, 'قام المدير ' . $user->name . ' بإلغاء مراجعة المصروف #' . $expense->id . ' وفتح التعديل مرة أخرى');

        $message = 'تم إلغاء المراجعة وفتح التعديل على المصروف';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function quickStore(Request $request)
    {
        $this->authorize('spend_money');

        try {
            $validated = $request->validate([
                'custody_id' => 'required|exists:custodies,id',
                'expense_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'required|string|max:500',
                'line_items' => 'nullable|string|max:1000',
                'is_quick_expense' => 'required|boolean',
            ]);

            // Verify custody access
            $custody = Custody::findOrFail($validated['custody_id']);
            $user = auth()->user();

            // Agents can only spend from their own custodies
            if ($user->hasRole('مندوب') && $custody->agent_id !== $user->id) {
                return response()->json(['success' => false, 'message' => 'غير مصرح لك بصرف من هذه العهدة'], 403);
            }
            // Managers and accountants can spend from any custody


            // Check custody has enough balance
            $remaining = $custody->getRemainingBalance();
            if ($validated['amount'] > $remaining) {
                return response()->json([
                    'success' => false,
                    'message' => 'المبلغ يتجاوز الرصيد المتاح. الرصيد المتاح: ' . number_format($remaining, 2) . ' ج.م'
                ], 422);
            }

            // Use transaction to ensure all operations succeed or all fail
            $expense = DB::transaction(function () use ($validated, $custody) {
                // Create expense with quick flag
                $expense = Expense::create([
                    'custody_id' => $validated['custody_id'],
                    'treasury_id' => $custody->treasury_id,
                    'user_id' => auth()->id(),
                    'expense_date' => $validated['expense_date'],
                    'amount' => $validated['amount'],
                    'description' => $validated['description'],
                    'type' => 'general',
                    'approval_status' => 'pending_edit',
                    'is_quick_expense' => true,
                    'line_items' => $validated['line_items'] ? json_encode(['raw_text' => $validated['line_items']]) : null,
                ]);

                // Update custody spent amount
                $custody->increment('spent', $validated['amount']);

                // Create treasury transaction for tracking
                if ($custody->treasury_id) {
                    DB::table('treasury_transactions')->insert([
                        'treasury_id' => $custody->treasury_id,
                        'custody_id' => $custody->id,
                        'type' => 'expense',
                        'amount' => $validated['amount'],
                        'description' => 'مصروف سريع: ' . $validated['description'],
                        'transaction_date' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Log the activity
                ActivityLogService::created($expense, 'تم تسجيل مصروف سريع بمبلغ ' . number_format($validated['amount'], 2) . ' ج.م من العهدة #' . $custody->id);

                return $expense;
            });

            // Send notification to managers and accountants for review
            $user = auth()->user();
            $notificationMessage = "المستخدم: {$user->name} - سجل مصروفاً سريعاً بمبلغ " . number_format($expense->amount, 2) . " ج.م - الوصف: {$expense->description}";
            NotificationService::notifyByRole('مدير', 'مصروف جديد للمراجعة', $notificationMessage, 'warning', $expense->id, 'expense');
            NotificationService::notifyByRole('محاسب', 'مصروف جديد للمراجعة', $notificationMessage, 'warning', $expense->id, 'expense');

            return response()->json(['success' => true, 'message' => 'تم تسجيل المصروف بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('spend_money');

        // Check authorization: only accountants and managers can delete
        $user = auth()->user();
        $isAccountantOrManager = $user->hasRole('محاسب') || $user->hasRole('مدير');

        if (!$isAccountantOrManager) {
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
