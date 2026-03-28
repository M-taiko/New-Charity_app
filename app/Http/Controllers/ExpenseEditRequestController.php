<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseEditRequest;
use App\Services\ExpenseEditRequestService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

class ExpenseEditRequestController extends Controller
{
    public function __construct(private ExpenseEditRequestService $service) {}

    /**
     * عرض نموذج طلب التعديل
     */
    public function create(Expense $expense)
    {
        // التحقق من أن المندوب هو صاحب المصروف
        if ($expense->user_id !== auth()->id()) {
            abort(403, 'غير مصرح لك بتعديل هذا المصروف');
        }

        // التحقق من أنه لا توجد تعديلات معلقة
        if ($expense->hasPendingEdit()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'هناك طلب تعديل معلق على هذا المصروف بالفعل');
        }

        // التحقق من أن المصروف ليس معتمداً
        if ($expense->isApproved()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'المصروف معتمد ولا يمكن طلب تعديل عليه');
        }

        // جلب البيانات الحالية
        $categories = \App\Models\ExpenseCategory::active()->with('items')->ordered()->get();

        return view('expenses.edit-request', compact('expense', 'categories'));
    }

    /**
     * حفظ طلب التعديل
     */
    public function store(Request $request, Expense $expense)
    {
        // التحقق من الصلاحيات
        if ($expense->user_id !== auth()->id()) {
            abort(403);
        }

        // Validation
        $rules = [
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'description' => 'required|string|max:500',
            'location' => 'nullable|string|max:255',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'expense_item_id' => 'nullable|exists:expense_items,id',
            'social_case_id' => 'nullable|exists:social_cases,id',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048',
            'reason' => 'nullable|string|max:500',
        ];

        $validated = $request->validate($rules);

        try {
            // معالجة المرفق إذا تم تحميله
            if ($request->hasFile('attachment')) {
                $attachment = $request->file('attachment')->store('expense_attachments', 'public');
                $validated['attachment'] = $attachment;
            } else {
                unset($validated['attachment']);
            }

            // إنشاء طلب التعديل
            $editRequest = $this->service->requestEdit($expense, $validated, auth()->user());

            return redirect()->route('expenses.show', $expense)
                ->with('success', 'تم إرسال طلب التعديل للمحاسب والمدير بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * قائمة طلبات التعديل المعلقة (للمحاسب والمدير)
     */
    public function index(Request $request)
    {
        $this->authorize('manage_treasury');

        if ($request->expectsJson()) {
            return $this->tableData();
        }

        $pendingCount = ExpenseEditRequest::where('status', 'pending')->count();
        $approvedCount = ExpenseEditRequest::where('status', 'approved')->count();
        $rejectedCount = ExpenseEditRequest::where('status', 'rejected')->count();

        return view('expense-edit-requests.index', compact(
            'pendingCount',
            'approvedCount',
            'rejectedCount'
        ));
    }

    /**
     * بيانات الجدول
     */
    private function tableData()
    {
        $editRequests = ExpenseEditRequest::with(['expense', 'requester', 'reviewer'])
            ->orderBy('requested_at', 'desc')
            ->get();

        return DataTables::of($editRequests)
            ->addColumn('expense_id', fn($row) => "#{$row->expense_id}")
            ->addColumn('requester_name', fn($row) => $row->requester->name ?? '-')
            ->addColumn('expense_amount', fn($row) => number_format($row->expense->amount, 2) . ' ج.م')
            ->addColumn('requested_amount', fn($row) => number_format($row->requested_changes['amount'] ?? 0, 2) . ' ج.م')
            ->addColumn('status_badge', function($row) {
                $badges = [
                    'pending' => '<span class="badge bg-warning">معلق</span>',
                    'approved' => '<span class="badge bg-success">موافق</span>',
                    'rejected' => '<span class="badge bg-danger">مرفوض</span>',
                ];
                return $badges[$row->status] ?? '';
            })
            ->addColumn('actions', function($row) {
                return view('partials.expense-edit-request-actions', compact('row'))->render();
            })
            ->rawColumns(['status_badge', 'actions'])
            ->toJson();
    }

    /**
     * عرض تفاصيل طلب التعديل
     */
    public function show(ExpenseEditRequest $editRequest)
    {
        $this->authorize('manage_treasury');

        $editRequest->load(['expense', 'requester', 'reviewer']);

        return view('expense-edit-requests.show', compact('editRequest'));
    }

    /**
     * الموافقة على طلب التعديل
     */
    public function approve(ExpenseEditRequest $editRequest)
    {
        $this->authorize('manage_treasury');

        if (!$editRequest->isPending()) {
            return back()->with('error', 'لا يمكن الموافقة على هذا الطلب - الحالة غير صحيحة');
        }

        try {
            $this->service->approveEdit($editRequest, auth()->user());

            return redirect()->route('expense-edit-requests.show', $editRequest)
                ->with('success', 'تمت الموافقة على التعديل بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * رفض طلب التعديل
     */
    public function reject(Request $request, ExpenseEditRequest $editRequest)
    {
        $this->authorize('manage_treasury');

        if (!$editRequest->isPending()) {
            return back()->with('error', 'لا يمكن رفض هذا الطلب - الحالة غير صحيحة');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $this->service->rejectEdit($editRequest, auth()->user(), $validated['rejection_reason']);

            return redirect()->route('expense-edit-requests.index')
                ->with('success', 'تم رفض طلب التعديل');
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }
}
