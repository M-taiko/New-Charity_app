<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('مدير') || $user->hasRole('محاسب') || $user->hasRole('مشرف')) {
            $requests = PurchaseRequest::with(['requester', 'supplier'])->latest()->get();
        } else {
            $requests = PurchaseRequest::with(['requester', 'supplier'])
                ->where('requested_by', $user->id)->latest()->get();
        }

        $stats = [
            'pending'   => PurchaseRequest::where('status', 'pending')->count(),
            'approved'  => PurchaseRequest::where('status', 'approved')->count(),
            'purchased' => PurchaseRequest::where('status', 'purchased')->count(),
            'total_cost'=> PurchaseRequest::where('status', 'purchased')->sum('actual_cost'),
        ];

        return view('procurement.purchase-requests.index', compact('requests', 'stats'));
    }

    public function create()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        return view('procurement.purchase-requests.create', compact('suppliers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string|max:2000',
            'category'       => 'required|in:office_supplies,equipment,services,maintenance,other',
            'priority'       => 'required|in:low,medium,high,urgent',
            'estimated_cost' => 'nullable|numeric|min:0',
            'needed_by'      => 'nullable|date',
            'supplier_id'    => 'nullable|exists:suppliers,id',
            'attachment'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $data = $request->only(['title', 'description', 'category', 'priority', 'estimated_cost', 'needed_by', 'supplier_id']);
        $data['requested_by'] = auth()->id();
        $data['status'] = 'pending';

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('purchase-requests', 'public');
        }

        $pr = PurchaseRequest::create($data);

        // Notify managers
        $managers = User::role('مدير')->get();
        foreach ($managers as $manager) {
            NotificationService::notifyUser(
                $manager->id,
                'طلب شراء جديد',
                auth()->user()->name . ' أرسل طلب شراء: ' . $pr->title,
                'warning',
                $pr->id,
                'purchase_request'
            );
        }

        ActivityLogService::log('created', 'طلب شراء جديد: ' . $pr->title, $pr);

        return redirect()->route('purchase-requests.show', $pr)
            ->with('success', 'تم إرسال طلب الشراء للمراجعة');
    }

    public function show(PurchaseRequest $purchaseRequest)
    {
        $purchaseRequest->load(['requester', 'reviewer', 'supplier', 'treasury', 'expenseCategory']);

        // Get all treasuries with balances
        $treasuries = \App\Models\Treasury::all();

        // Get all active expense categories (root level)
        $expenseCategories = \App\Models\ExpenseCategory::roots()->active()->ordered()->get();

        return view('procurement.purchase-requests.show', compact('purchaseRequest', 'treasuries', 'expenseCategories'));
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeManager();

        // Validate treasury and category selection
        $request->validate([
            'treasury_id' => 'required|exists:treasuries,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'treasury_amounts' => 'required|array|min:1',
            'treasury_amounts.*' => 'numeric|min:0.01',
        ]);

        // Calculate total from distribution
        $totalAmount = array_sum($request->treasury_amounts ?? []);
        $estimatedCost = $purchaseRequest->estimated_cost ?? 0;

        if (abs($totalAmount - $estimatedCost) > 0.01) {
            return back()->withInput()->with('error',
                'إجمالي المبالغ المدفوعة (' . number_format($totalAmount, 2) . ') يجب أن يساوي التكلفة التقديرية (' . number_format($estimatedCost, 2) . ')');
        }

        // Check all treasuries have sufficient balance
        foreach ($request->treasury_amounts as $treasuryId => $amount) {
            $treasury = \App\Models\Treasury::findOrFail($treasuryId);
            if ($treasury->balance < $amount) {
                return back()->withInput()->with('error',
                    'رصيد الخزينة "' . $treasury->name . '" (' . number_format($treasury->balance, 2) . ') غير كافي للمبلغ المطلوب (' . number_format($amount, 2) . ')');
            }
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $purchaseRequest, $totalAmount) {
                // Update purchase request with allocation details
                $purchaseRequest->update([
                    'status'               => 'approved',
                    'treasury_id'          => $request->treasury_id,
                    'expense_category_id'  => $request->expense_category_id,
                    'treasury_distribution' => $request->treasury_amounts,
                    'reviewed_by'          => auth()->id(),
                    'reviewed_at'          => now(),
                ]);

                // Deduct from all allocated treasuries and record transactions
                foreach ($request->treasury_amounts as $treasuryId => $amount) {
                    if ($amount > 0) {
                        $treasury = \App\Models\Treasury::findOrFail($treasuryId);
                        $treasury->decrement('balance', $amount);

                        // Record transaction for audit trail
                        \App\Models\TreasuryTransaction::create([
                            'treasury_id' => $treasuryId,
                            'type' => 'purchase_request',
                            'amount' => $amount,
                            'description' => 'صرف لطلب شراء: ' . $purchaseRequest->title,
                            'user_id' => auth()->id(),
                            'transaction_date' => now(),
                            'reference_id' => $purchaseRequest->id,
                            'reference_type' => 'purchase_request',
                        ]);
                    }
                }
            });

            NotificationService::notifyUser(
                $purchaseRequest->requested_by,
                'تمت الموافقة على طلب شرائك',
                'تم الموافقة على طلب: ' . $purchaseRequest->title . ' بمبلغ ' . number_format($totalAmount, 2) . ' ج.م',
                'success',
                $purchaseRequest->id,
                'purchase_request'
            );

            ActivityLogService::approved($purchaseRequest, 'موافقة على طلب شراء: ' . $purchaseRequest->title . ' - صرف ' . number_format($totalAmount, 2) . ' ج.م');

            return back()->with('success', 'تمت الموافقة على الطلب وتسجيل الصرف من الخزائن');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeManager();

        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $purchaseRequest->update([
            'status'           => 'rejected',
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        NotificationService::notifyUser(
            $purchaseRequest->requested_by,
            'تم رفض طلب شرائك',
            'تم رفض طلب: ' . $purchaseRequest->title . '. السبب: ' . $request->rejection_reason,
            'danger',
            $purchaseRequest->id,
            'purchase_request'
        );

        ActivityLogService::rejected($purchaseRequest, 'رفض طلب شراء: ' . $purchaseRequest->title);

        return back()->with('success', 'تم رفض الطلب');
    }

    public function markPurchased(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->authorizeManager();

        $request->validate(['actual_cost' => 'nullable|numeric|min:0']);

        $purchaseRequest->update([
            'status'      => 'purchased',
            'actual_cost' => $request->actual_cost ?? $purchaseRequest->estimated_cost,
        ]);

        NotificationService::notifyUser(
            $purchaseRequest->requested_by,
            'تم شراء الطلب',
            'تم تنفيذ طلب الشراء: ' . $purchaseRequest->title,
            'success',
            $purchaseRequest->id,
            'purchase_request'
        );

        ActivityLogService::log('completed', 'تم شراء: ' . $purchaseRequest->title, $purchaseRequest);

        return back()->with('success', 'تم تسجيل الشراء');
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $user = auth()->user();
        if ($purchaseRequest->requested_by !== $user->id && !$user->hasRole('مدير')) {
            abort(403);
        }
        $purchaseRequest->delete();
        return redirect()->route('purchase-requests.index')->with('success', 'تم حذف الطلب');
    }

    private function authorizeManager(): void
    {
        if (!auth()->user()->hasRole('مدير') && !auth()->user()->hasRole('محاسب')) {
            abort(403);
        }
    }
}
