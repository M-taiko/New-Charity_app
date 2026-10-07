<?php

namespace App\Http\Controllers;

use App\Models\Custody;
use App\Models\Treasury;
use App\Models\User;
use App\Models\CustodyReturnRequest;
use App\Models\Notification;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Services\StatusLabelService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustodyController extends Controller
{
    public function __construct(private TreasuryService $service) {}

    public function index()
    {
        $this->authorize('manage_treasury');
        return view('custodies.modern');
    }

    public function create(Request $request)
    {
        $isAgent = auth()->user()->hasRole('مندوب');
        $forType = $request->query('for'); // 'self' or 'agent'

        // Agents can request custody for themselves
        // Accountants and managers can create custody for agents or request for themselves
        if (!$isAgent) {
            $this->authorize('create_custody');
        }

        // Get all treasuries for selection (only needed for agent requests)
        $treasuries = Treasury::all();

        if ($treasuries->isEmpty()) {
            return redirect()->route('custodies.index')->with('error', 'لم يتم العثور على خزائن. يرجى الاتصال بالمسؤول.');
        }

        // Auto-route based on request type or user role
        // If no explicit for_type specified, route based on user role:
        // - Agents get agent-request form
        // - Non-agents (accountants/managers) get personal-request form
        if ($forType === 'agent') {
            // Creating custody for a user (accountant/manager creates for someone else)
            // Exclude specific email and hidden users
            $users = User::where('email', '!=', 'donia.a5ra2019@gmail.com')
                ->orderBy('name')
                ->get();

            return view('custodies.create-for-agent', compact('users', 'treasuries'));
        } elseif ($forType === 'self') {
            // Personal request (accountant/manager requests for themselves)
            return view('custodies.personal-request', compact('treasuries'));
        } else {
            // Default routing: agents get agent-request form, non-agents get personal-request form
            if ($isAgent) {
                return view('custodies.agent-request', compact('treasuries'));
            } else {
                return view('custodies.personal-request', compact('treasuries'));
            }
        }
    }

    public function store(Request $request)
    {
        $isAgent = auth()->user()->hasRole('مندوب');
        $isForSelf = $request->has('for_self') || (!$isAgent && !$request->filled('agent_id')); // Accountant/Manager requesting for themselves

        if (!$isAgent && !$isForSelf) {
            $this->authorize('create_custody');
        }

        // Build validation rules
        // treasury_id is required only when explicitly selecting a user (create-for-agent form)
        // For agents and personal custody, treasury_id is determined by manager during approval
        $validationRules = [
            'agent_id' => ($isAgent || $isForSelf) ? 'nullable' : 'required|exists:users,id',
            'treasury_id' => ($isForSelf || $isAgent || !$request->filled('agent_id')) ? 'nullable' : 'required|exists:treasuries,id',
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:1000000', // Reasonable maximum
            ],
            'issued_date' => 'required|date',
            'notes' => 'nullable|string',
        ];

        // Get selected treasury for validation and use
        $treasuryId = $request->input('treasury_id');
        if ($treasuryId) {
            $treasury = Treasury::findOrFail($treasuryId);
            // Add max amount rule only if treasury is selected
            $validationRules['amount'][] = 'max:' . $treasury->balance;
            $validationMessages = ['amount.max' => 'المبلغ المدخل يتجاوز رصيد الخزينة المختارة. الحد الأقصى: ' . number_format($treasury->balance, 2) . ' ج.م'];
        } else {
            // For personal requests without selected treasury, use null treasury
            $treasury = null;
            $validationMessages = [];
        }

        $request->validate($validationRules, $validationMessages);

        // Determine agent ID:
        // - If agent_id is provided in request (creating for another user), use it
        // - If for_self is checked (accountant/manager creating for themselves), use current user ID
        // - Otherwise (agent requesting custody), use current user ID
        if ($request->filled('agent_id') && $request->agent_id != auth()->id()) {
            // Creating custody for another agent
            $agentId = $request->agent_id;
            $isAgentRequest = false; // Accountant creating for agent, not agent request
        } else {
            // Creating custody for self (either agent request or accountant for_self)
            $agentId = auth()->id();
            $isAgentRequest = $isAgent; // True if agent requesting, false if accountant for self
        }

        try {
            $custody = $this->service->createCustody(
                $treasury?->id,  // Can be null for personal requests
                $agentId,
                auth()->id(),
                $request->amount,
                $request->notes,
                $isAgentRequest,
                $isForSelf  // Pass personal custody flag
            );

            // Log the activity with the custody as subject
            if ($isAgentRequest) {
                $message = 'تم إرسال طلب العهدة للمحاسب للموافقة';
                ActivityLogService::created($custody, 'طلب عهدة جديد بمبلغ ' . number_format($request->amount, 2) . ' ج.م');
            } elseif ($isForSelf) {
                // Personal custody - requires explicit approval workflow
                $message = 'تم إنشاء طلب العهدة الشخصية. يرجى انتظار موافقة مدير';
                ActivityLogService::created($custody, 'طلب عهدة شخصية بمبلغ ' . number_format($request->amount, 2) . ' ج.م من قبل ' . auth()->user()->name);
            } else {
                $message = 'تم إنشاء العهدة بنجاح';
                ActivityLogService::created($custody, 'إنشاء عهدة بمبلغ ' . number_format($request->amount, 2) . ' ج.م');
            }

            return redirect()->route($isAgentRequest ? 'agent.transactions' : 'custodies.index')->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(Custody $custody)
    {
        return view('custodies.modern-show', compact('custody'));
    }

    public function edit(Custody $custody)
    {
        $this->authorize('manage_treasury');
        return view('custodies.modern-edit', compact('custody'));
    }

    public function update(Request $request, Custody $custody)
    {
        $this->authorize('manage_treasury');
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'notes' => 'nullable|string',
        ]);

        $custody->update($request->only(['amount', 'notes']));

        return redirect()->route('custodies.index')->with('success', 'تم تحديث العهدة بنجاح');
    }

    public function accept(Custody $custody, Request $request)
    {
        $this->authorize('approve_custody');

        // Handle auto-approve for accountant-created custodies
        if ($request->has('auto_approve') && $custody->initiated_by === 'accountant') {
            try {
                $this->service->acceptCustody($custody);
                ActivityLogService::approved($custody, 'تم الموافقة على العهدة #' . $custody->id . ' للمندوب ' . ($custody->agent?->name ?? 'غير محدد'));
                return $this->formBackOrJson($request, 'success', 'تم الموافقة على العهدة. المندوب سيستقبل الأموال من خزينة "' . ($custody->treasury->name ?? 'غير محددة') . '" عند القبول');
            } catch (\Exception $e) {
                return $this->formBackOrJson($request, 'error', $e->getMessage());
            }
        }

        // Handle personal custody (treasury_id is null)
        if ($custody->treasury_id === null) {
            // Personal custody - select single treasury
            $treasuryId = $request->input('treasury_id');

            if (!$treasuryId) {
                return $this->formBackOrJson($request, 'error', 'يرجى اختيار خزينة');
            }

            try {
                $treasury = Treasury::findOrFail($treasuryId);
                $this->service->acceptPersonalCustodyFromTreasury($custody, $treasury);
                ActivityLogService::approved($custody, 'تم الموافقة على العهدة الشخصية #' . $custody->id . ' للموظف ' . ($custody->agent?->name ?? 'غير محدد'));
                return $this->formBackOrJson($request, 'success', 'تم الموافقة على العهدة الشخصية وصرف الأموال من خزينة "' . $treasury->name . '" بنجاح');
            } catch (\Exception $e) {
                return $this->formBackOrJson($request, 'error', $e->getMessage());
            }
        }

        // Get treasury amounts distribution for regular custodies
        $treasuryAmounts = $request->input('treasury_amounts', []);

        if (empty($treasuryAmounts) || !array_filter($treasuryAmounts)) {
            return $this->formBackOrJson($request, 'error', 'يرجى توزيع المبالغ على الخزائن');
        }

        // Calculate total and validate
        $totalAmount = 0;
        $distribution = [];

        foreach ($treasuryAmounts as $treasuryId => $amount) {
            $amount = (float) $amount;
            if ($amount > 0) {
                $treasury = Treasury::findOrFail($treasuryId);

                // Validate treasury has enough balance
                if ($treasury->balance < $amount) {
                    return $this->formBackOrJson($request, 'error', "رصيد خزينة '{$treasury->name}' غير كافي. المطلوب: {$amount}, المتاح: {$treasury->balance}");
                }

                $distribution[$treasuryId] = [
                    'treasury' => $treasury,
                    'amount' => $amount
                ];
                $totalAmount += $amount;
            }
        }

        // Validate total equals custody amount
        if (abs($totalAmount - $custody->amount) > 0.01) {
            return $this->formBackOrJson($request, 'error', 'مجموع المبالغ المتوزعة يجب أن يساوي ' . number_format($custody->amount, 2) . ' ج.م');
        }

        try {
            $this->service->acceptCustodyWithDistribution($custody, $distribution);
            ActivityLogService::approved($custody, 'تم الموافقة على العهدة #' . $custody->id . ' للمندوب ' . ($custody->agent?->name ?? 'غير محدد'));
            return $this->formBackOrJson($request, 'success', 'تم الموافقة على العهدة وتوزيع الأموال بنجاح');
        } catch (\Exception $e) {
            return $this->formBackOrJson($request, 'error', $e->getMessage());
        }
    }

    public function receive(Custody $custody)
    {
        // Only the agent who owns the custody can receive it
        if ($custody->agent_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        try {
            $this->service->receiveCustody($custody);
            return back()->with('success', 'تم استقبال العهدة وصرف الفلوس');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Custody $custody, Request $request)
    {
        $this->authorize('approve_custody');

        try {
            $this->service->rejectCustody($custody, $request->reason);
            ActivityLogService::rejected($custody, 'تم رفض العهدة #' . $custody->id . ' للمندوب ' . $custody->agent->name);
            return $this->formBackOrJson($request, 'success', 'تم رفض العهدة');
        } catch (\Exception $e) {
            return $this->formBackOrJson($request, 'error', $e->getMessage());
        }
    }

    public function agentAccept(Custody $custody, Request $request)
    {
        // Use the treasury that was pre-determined when the custody was created
        // The agent cannot choose a different treasury
        $treasury = $custody->treasury;

        if (!$treasury) {
            return back()->with('error', 'لم يتم تحديد خزينة للعهدة. يرجى الاتصال بالمحاسب');
        }

        // Validate that treasury has enough balance
        if ($treasury->balance < $custody->amount) {
            return back()->with('error', 'رصيد الخزينة "' . $treasury->name . '" غير كافي. الرصيد المتاح: ' . number_format($treasury->balance, 2) . ' ج.م. يرجى الاتصال بالمحاسب');
        }

        try {
            $this->service->agentAcceptCustodyFromTreasury($custody, $treasury);
            return back()->with('success', 'تم قبول العهدة من خزينة "' . $treasury->name . '" وصرف الأموال بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function agentReject(Custody $custody, Request $request)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->service->agentRejectCustody($custody, $request->reason);
            return $this->formBackOrJson($request, 'success', 'تم رفض العهدة');
        } catch (\Exception $e) {
            return $this->formBackOrJson($request, 'error', $e->getMessage());
        }
    }

    public function return(Custody $custody, Request $request)
    {
        // Only the agent who owns this custody can request to return it
        if ($custody->agent_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        // Check if there are pending transfers
        if ($custody->hasPendingTransfers()) {
            return $this->formBackOrJson($request, 'error', 'لا يمكن رد عهدة بها تحويلات معلقة. يرجى انتظار قبول أو رفض التحويلات أولاً');
        }

        $remainingBalance = $custody->getRemainingBalance();

        $request->validate([
            'returned_amount' => 'required|numeric|min:0.01|max:' . $remainingBalance,
        ]);

        $this->service->requestReturnCustody($custody, $request->returned_amount);
        ActivityLogService::returned($custody, 'طلب رد ' . number_format($request->returned_amount, 2) . ' ج.م من العهدة #' . $custody->id);
        return $this->formBackOrJson($request, 'success', 'تم إرسال طلب رد العهدة للمحاسب');
    }

    public function addExternalDonation(Request $request, Custody $custody)
    {
        // Allow custody owner or users with manage_treasury permission
        if (auth()->id() !== $custody->agent_id && !auth()->user()->can('manage_treasury')) {
            return $this->formBackOrJson($request, 'error', 'غير مصرح لك بإضافة تبرعات لهذه العهدة');
        }

        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'type'        => 'required|in:external_donation,expense_refund',
        ]);

        try {
            // التبرع الخارجي = عهدة مستقلة جديدة (أدق في الحسابات وأوضح)
            // استرداد المصروف = يُعاد لنفس العهدة (المبلغ أصلاً صُرف منها)
            if ($request->type === 'external_donation') {
                $newCustody = $this->service->addExternalDonationAsNewCustody(
                    $custody,
                    $request->amount,
                    $request->description,
                    $request->type
                );
                return $this->formBackOrJson($request, 'success', 'تم إنشاء عهدة جديدة مستقلة #' . $newCustody->id . ' بمبلغ التبرع');
            }

            $this->service->addExternalDonationToCustody($custody, $request->amount, $request->description, $request->type);
            return $this->formBackOrJson($request, 'success', 'تم إضافة المبلغ لرصيد العهدة بنجاح');
        } catch (\Exception $e) {
            return $this->formBackOrJson($request, 'error', $e->getMessage());
        }
    }

    public function addRecovery(Request $request)
    {
        // Allow agent to add recovery funds
        if (!auth()->user()->hasRole('مندوب')) {
            return $this->formBackOrJson($request, 'error', 'غير مصرح لك بتسجيل استرجاع');
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'custody_id' => 'nullable|exists:custodies,id|sometimes',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $user = auth()->user();
                $custody = null;

                if ($request->filled('custody_id')) {
                    // Add to existing custody
                    $custody = Custody::findOrFail($request->custody_id);

                    // Verify ownership
                    if ($custody->agent_id !== $user->id) {
                        throw new \Exception('غير مصرح لك بإضافة أموال لهذه العهدة');
                    }

                    $custody->increment('amount', $request->amount);
                } else {
                    // Create new custody for recovery
                    $treasuries = Treasury::all();
                    $defaultTreasury = $treasuries->first();

                    if (!$defaultTreasury) {
                        throw new \Exception('لا توجد خزائن متاحة');
                    }

                    $custody = Custody::create([
                        'agent_id' => $user->id,
                        'accountant_id' => null,
                        'treasury_id' => $defaultTreasury->id,
                        'amount' => $request->amount,
                        'spent' => 0,
                        'returned' => 0,
                        'status' => 'accepted',
                        'reason' => 'استرجاع: ' . $request->description,
                    ]);
                }

                // Create transaction
                TreasuryTransaction::create([
                    'treasury_id' => $custody->treasury_id,
                    'type' => 'recovery',
                    'amount' => $request->amount,
                    'description' => "استرجاع أموال للمندوب {$user->name}: {$request->description}",
                    'user_id' => $user->id,
                    'custody_id' => $custody->id,
                    'transaction_date' => now(),
                ]);

                ActivityLogService::logged(
                    "تم تسجيل استرجاع بمبلغ " . number_format($request->amount, 2) . " ج.م",
                    'Custody',
                    $custody->id
                );

                // Send notifications to accountants and managers
                $notificationMessage = "المندوب: {$user->name} - استرجع " . number_format($request->amount, 2) . " ج.م - السبب: {$request->description}";
                NotificationService::notifyByRoles(['محاسب', 'مدير'], 'استرجاع أموال جديد', $notificationMessage, 'info', $custody->id, 'custody');
            });

            return $this->formBackOrJson($request, 'success', 'تم تسجيل الاسترجاع بنجاح وإرسال إشعار للمحاسب والمدير');
        } catch (\Exception $e) {
            \Log::error('Recovery error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->formBackOrJson($request, 'error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    public function requestReturnTreasury(Request $request)
    {
        $request->validate([
            'custody_id' => 'required|exists:custodies,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
        ]);

        $custody = Custody::findOrFail($request->custody_id);

        // T28 rev2: المندوب كالمعتاد، وأيضاً أي مالك للعهدة المذكورة (غير المشرف)
        // — باحث أو غيره استلم عهدة عبر الاستلام الخارجي يردها للخزينة من هنا
        $user = auth()->user();
        if (!$user->hasRole('مندوب')) {
            $isOwnerNonSupervisor = !$user->hasRole('مشرف') && $custody->agent_id === $user->id;
            if (!$isOwnerNonSupervisor) {
                return $this->formBackOrJson($request, 'error', 'غير مصرح لك بتقديم طلب رد');
            }
        }

        if (auth()->id() !== $custody->agent_id) {
            return $this->formBackOrJson($request, 'error', 'غير مصرح لك برد من هذه العهدة');
        }

        $remainingBalance = $custody->getRemainingBalance();
        if ($request->amount > $remainingBalance) {
            return $this->formBackOrJson($request, 'error', 'المبلغ يتجاوز الرصيد المتاح (' . number_format($remainingBalance, 2) . ' ج.م)', true);
        }

        try {
            // Create a return request that accountant will review and process
            CustodyReturnRequest::create([
                'custody_id' => $custody->id,
                'requested_by' => auth()->id(),
                'amount' => $request->amount,
                'reason' => $request->description,
                'status' => 'pending',
            ]);

            ActivityLogService::logged(
                "تم تقديم طلب رد " . number_format($request->amount, 2) . " ج.م من العهدة",
                'Custody',
                $custody->id
            );

            // Notify accountants and managers about the return request
            $user = auth()->user();
            $notificationMessage = "المندوب: {$user->name} - طلب رد " . number_format($request->amount, 2) . " ج.م من العهدة #" . $custody->id . " - السبب: " . $request->description;
            NotificationService::notifyByRoles(['محاسب', 'مدير'], 'طلب رد عهدة جديد', $notificationMessage, 'warning', $custody->id, 'custody');

            return $this->formBackOrJson($request, 'success', 'تم تقديم طلب الرد بنجاح. سيقوم المحاسب بمراجعة الطلب واختيار الخزينة المناسبة');
        } catch (\Exception $e) {
            \Log::error('Return request error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->formBackOrJson($request, 'error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    public function approveReturn(Request $request, Custody $custody)
    {
        $this->authorize('approve_custody');

        if ($custody->pending_return <= 0) {
            return $this->formBackOrJson($request, 'error', 'لا يوجد مبلغ معلق للموافقة عليه');
        }

        $request->validate([
            'treasury_id' => 'required|exists:treasuries,id',
        ], [
            'treasury_id.required' => 'يرجى اختيار خزينة',
            'treasury_id.exists' => 'الخزينة المختارة غير موجودة',
        ]);

        $pendingAmount = $custody->pending_return;
        $treasuryId = $request->input('treasury_id');

        $this->service->approveCustodyReturn($custody, $treasuryId);
        ActivityLogService::approved($custody, 'تم قبول رد ' . number_format($pendingAmount, 2) . ' ج.م من العهدة #' . $custody->id . ' للمندوب ' . $custody->agent->name . ' وإضافتها إلى الخزينة');
        return $this->formBackOrJson($request, 'success', 'تم قبول رد العهدة والتحويل للخزينة المختارة');
    }

    /**
     * Request to return part of custody (accounting request to custody owner)
     */
    public function requestReturn(Request $request, Custody $custody)
    {
        $this->authorize('approve_custody');

        // Check if there are pending transfers
        if ($custody->hasPendingTransfers()) {
            return back()->with('error', 'لا يمكن طلب رد عهدة بها تحويلات معلقة. يرجى انتظار قبول أو رفض التحويلات أولاً');
        }

        // Check if there's already a pending return request
        if ($custody->hasPendingReturnRequest()) {
            return back()->with('error', 'يوجد طلب رد معلق بالفعل على هذه العهدة. يرجى الانتظار لحين الموافقة عليه');
        }

        $remaining = $custody->getRemainingBalance();

        $request->validate([
            'return_amount' => 'required|numeric|min:0.01|max:' . $remaining,
            'reason' => 'nullable|string|max:500',
        ], [
            'return_amount.max' => 'المبلغ يتجاوز الرصيد المتاح (' . number_format($remaining, 2) . ' ج.م)',
        ]);

        try {
            $returnRequest = CustodyReturnRequest::create([
                'custody_id' => $custody->id,
                'requested_by' => auth()->id(),
                'amount' => $request->return_amount,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            // Notify custody owner (the one who initiated the custody)
            $custodyOwner = $custody->initiated_by === 'agent'
                ? $custody->agent_id
                : $custody->accountant_id;

            Notification::create([
                'user_id' => $custodyOwner,
                'title' => 'طلب رد عهدة',
                'message' => 'المحاسب ' . auth()->user()->name . ' يطلب رد ' . number_format($request->return_amount, 2) . ' ج.م من العهدة #' . $custody->id,
                'type' => 'info',
                'reference_id' => $returnRequest->id,
                'reference_type' => 'custody_return_request',
            ]);

            ActivityLogService::logged('تم تقديم طلب رد عهدة', 'CustodyReturnRequest', $returnRequest->id);
            return back()->with('success', 'تم تقديم طلب الرد لصاحب العهدة بنجاح. يرجى انتظار الموافقة');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Approve return request (custody owner action only)
     */
    public function approveReturnRequest(CustodyReturnRequest $returnRequest)
    {
        if (!$returnRequest->isPending()) {
            return back()->with('error', 'لا يمكن الموافقة على طلب تم البت فيه بالفعل');
        }

        $custody = $returnRequest->custody;

        // Verify that the approver is the custody owner
        $custodyOwnerId = $custody->initiated_by === 'agent'
            ? $custody->agent_id
            : $custody->accountant_id;

        if (auth()->id() !== $custodyOwnerId) {
            abort(403, 'فقط صاحب العهدة يمكنه الموافقة على طلبات الرد');
        }

        try {
            DB::transaction(function () use ($returnRequest, $custody) {
                // Update request status
                $returnRequest->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);

                // Execute the return
                $this->service->returnCustody($custody, $returnRequest->amount);

                // Notify requester (the agent who requested the return)
                Notification::create([
                    'user_id' => $returnRequest->requested_by,
                    'title' => 'موافقة على طلب الرد',
                    'message' => 'تمت الموافقة على طلب رد ' . number_format($returnRequest->amount, 2) . ' ج.م من العهدة #' . $custody->id,
                    'type' => 'success',
                    'reference_id' => $returnRequest->id,
                    'reference_type' => 'custody_return_request',
                ]);

                // Notify accountants and managers about the approved return
                $user = auth()->user();
                $notificationMessage = "المستخدم: {$user->name} - وافق على رد " . number_format($returnRequest->amount, 2) . " ج.م من العهدة #" . $custody->id . " للمندوب " . $custody->agent->name;
                NotificationService::notifyByRoles(['محاسب', 'مدير'], 'رد عهدة موافق عليه', $notificationMessage, 'success', $custody->id, 'custody');

                ActivityLogService::returned($custody, 'رد مقبول ' . number_format($returnRequest->amount, 2) . ' ج.م من العهدة #' . $custody->id . ' بموافقة من المدير');
            });

            return back()->with('success', 'تمت الموافقة على طلب الرد وتنفيذه بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Approve return request and select treasury (accountant action)
     */
    public function approveReturnRequestWithTreasury(Request $request, CustodyReturnRequest $returnRequest)
    {
        // Check authorization: accountant or manager
        if (!auth()->user()->can('manage_treasury')) {
            abort(403, 'غير مصرح لك بالموافقة على طلبات الرد');
        }

        if (!$returnRequest->isPending()) {
            return $this->formBackOrJson($request, 'error', 'لا يمكن الموافقة على طلب تم البت فيه بالفعل');
        }

        $request->validate([
            'treasury_id' => 'required|exists:treasuries,id',
            'approval_notes' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($request, $returnRequest) {
                $custody = $returnRequest->custody;
                $treasury = Treasury::findOrFail($request->treasury_id);

                // Update request status
                $returnRequest->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'approval_notes' => $request->approval_notes,
                ]);

                // Deduct from custody
                $custody->increment('returned', $returnRequest->amount);

                // Add to selected treasury
                $treasury->increment('balance', $returnRequest->amount);

                // Create transaction in selected treasury
                TreasuryTransaction::create([
                    'treasury_id' => $request->treasury_id,
                    'type' => 'custody_return',
                    'amount' => $returnRequest->amount,
                    'description' => "رد عهدة من المندوب {$custody->agent->name} - {$returnRequest->reason}",
                    'user_id' => auth()->id(),
                    'custody_id' => $custody->id,
                    'transaction_date' => now(),
                ]);

                // Update custody status if fully returned (ولا يوجد تحويل معلق مجمّد — T26)
                if ($custody->returned >= $custody->amount && (float) $custody->pending_transfer_out <= 0) {
                    $custody->update(['status' => 'closed']);

                    TreasuryTransaction::create([
                        'treasury_id' => $request->treasury_id,
                        'type' => 'custody_close',
                        'amount' => 0,
                        'description' => "إقفال عهدة #$custody->id للمندوب {$custody->agent->name} - تم رد المبلغ بالكامل",
                        'user_id' => auth()->id(),
                        'custody_id' => $custody->id,
                        'transaction_date' => now(),
                    ]);
                } elseif ($custody->returned > 0) {
                    $custody->update(['status' => 'partially_returned']);
                }

                ActivityLogService::returned(
                    $custody,
                    'رد موافق عليه ' . number_format($returnRequest->amount, 2) . ' ج.م من العهدة #' . $custody->id . ' إلى خزينة ' . $treasury->name
                );

                // Notify agent about approval
                NotificationService::notifyUser(
                    $returnRequest->requested_by,
                    'موافقة على طلب الرد',
                    'تم الموافقة على رد ' . number_format($returnRequest->amount, 2) . ' ج.م من العهدة #' . $custody->id . ' إلى خزينة ' . $treasury->name,
                    'success',
                    $custody->id,
                    'custody'
                );

                // Notify other managers
                $user = auth()->user();
                $notificationMessage = "المحاسب: {$user->name} - وافق على رد " . number_format($returnRequest->amount, 2) . " ج.م من العهدة #" . $custody->id . " إلى خزينة " . $treasury->name;
                NotificationService::notifyByRole('مدير', 'رد عهدة موافق عليه', $notificationMessage, 'success', $custody->id, 'custody');
            });

            return $this->formBackOrJson($request, 'success', 'تمت الموافقة على طلب الرد وتحويل المبلغ للخزينة المختارة بنجاح');
        } catch (\Exception $e) {
            return $this->formBackOrJson($request, 'error', 'خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Reject return request (custody owner action only)
     */
    public function rejectReturnRequest(Request $request, CustodyReturnRequest $returnRequest)
    {
        if (!$returnRequest->isPending()) {
            return $this->formBackOrJson($request, 'error', 'لا يمكن رفض طلب تم البت فيه بالفعل');
        }

        $custody = $returnRequest->custody;

        // Verify that the rejector is the custody owner
        $custodyOwnerId = $custody->initiated_by === 'agent'
            ? $custody->agent_id
            : $custody->accountant_id;

        if (auth()->id() !== $custodyOwnerId) {
            abort(403, 'فقط صاحب العهدة يمكنه رفض طلبات الرد');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $returnRequest->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
                'approval_notes' => $request->rejection_reason,
                'approved_at' => now(),
            ]);

            // Notify requester
            Notification::create([
                'user_id' => $returnRequest->requested_by,
                'title' => 'رفض طلب الرد',
                'message' => 'تم رفض طلب رد ' . number_format($returnRequest->amount, 2) . ' ج.م من العهدة #' . $returnRequest->custody_id,
                'type' => 'warning',
                'reference_id' => $returnRequest->id,
                'reference_type' => 'custody_return_request',
            ]);

            ActivityLogService::logged('تم رفض طلب رد عهدة', 'CustodyReturnRequest', $returnRequest->id);
            return $this->formBackOrJson($request, 'success', 'تم رفض الطلب بنجاح');
        } catch (\Exception $e) {
            return $this->formBackOrJson($request, 'error', 'خطأ: ' . $e->getMessage());
        }
    }

    public function tableData()
    {
        $this->authorize('manage_treasury');
        $custodies = Custody::with(['agent', 'accountant'])->get();

        return DataTables::of($custodies)
            ->addColumn('agent_name', fn($row) => $row->agent?->name ?? '-')
            ->addColumn('transferred_in', fn($row) => (float)$row->transferred_in)
            ->addColumn('transferred_out', fn($row) => (float)$row->transferred_out)
            // نسبة الإنفاق على إجمالي ما دخل العهدة فعلياً (المبلغ + التحويلات الواردة)
            ->addColumn('spent_percent', fn($row) => ((float)$row->amount + (float)$row->transferred_in) > 0
                ? (int)round(($row->spent / ((float)$row->amount + (float)$row->transferred_in)) * 100)
                : 0)
            ->addColumn('remaining', fn($row) => (float)$row->getRemainingBalance())
            ->addColumn('status_label', fn($row) => $this->getStatusLabel($row->status))
            ->addColumn('status_detail', fn($row) => $row->status_detail)
            ->addColumn('actions', fn($row) => view('custodies.actions', compact('row'))->render())
            ->rawColumns(['status_label', 'status_detail', 'actions'])
            ->toJson();
    }

    private function getStatusLabel($status)
    {
        return StatusLabelService::label($status, 'custody');
    }

    public function agentTransactions()
    {
        $user = auth()->user();

        // Check if user is agent (مندوب)
        if (!$user->hasRole('مندوب')) {
            abort(403, 'Unauthorized');
        }

        // Get agent's custodies (all statuses for display)
        $custodies = Custody::where('agent_id', $user->id)->get();
        $custodiesCount = $custodies->count();

        // Calculate totals only for activated custodies (exclude pending/rejected)
        $activeCustodies = $custodies->whereIn('status', ['active', 'accepted', 'partially_returned', 'closed']);
        // العهد المستلم = المصروف من الخزينة + التحويلات الواردة من مندوبين آخرين
        $totalReceived = $activeCustodies->sum('amount') + $activeCustodies->sum('transferred_in');
        $totalSpent    = $activeCustodies->sum('spent');
        $totalReturned = $activeCustodies->sum('returned');
        $totalTransferredIn  = $activeCustodies->sum('transferred_in');
        $totalTransferredOut = $activeCustodies->sum('transferred_out');
        $totalRemaining = $activeCustodies->sum(fn ($c) => $c->getRemainingBalance());

        return view('custodies.agent-transactions', compact(
            'custodies',
            'custodiesCount',
            'totalReceived',
            'totalSpent',
            'totalReturned',
            'totalTransferredIn',
            'totalTransferredOut',
            'totalRemaining'
        ));
    }

    public function agentTransactionsData()
    {
        $user = auth()->user();

        // Check if user is agent (مندوب)
        if (!$user->hasRole('مندوب')) {
            abort(403, 'Unauthorized');
        }

        // Get agent's custodies
        $custodiesIds = Custody::where('agent_id', $user->id)->pluck('id');

        // Get all transactions for agent's custodies
        $transactions = \App\Models\TreasuryTransaction::whereIn('custody_id', $custodiesIds)
            ->orderBy('transaction_date', 'desc')
            ->get();

        return DataTables::of($transactions)
            ->addColumn('type', fn($row) => $row->type)
            ->addColumn('description', fn($row) => $row->description)
            ->addColumn('amount', fn($row) => $row->amount)
            ->addColumn('transaction_date', fn($row) => $row->transaction_date)
            ->toJson();
    }

    public function agentReturnedData()
    {
        $user = auth()->user();

        // Check if user is agent (مندوب)
        if (!$user->hasRole('مندوب')) {
            abort(403, 'Unauthorized');
        }

        // Get agent's custodies
        $custodiesIds = Custody::where('agent_id', $user->id)->pluck('id');

        // Get only return transactions
        $transactions = \App\Models\TreasuryTransaction::whereIn('custody_id', $custodiesIds)
            ->where('type', 'custody_return')
            ->with('custody')
            ->orderBy('transaction_date', 'desc')
            ->get();

        return DataTables::of($transactions)
            ->addColumn('type', fn($row) => 'رد عهدة')
            ->addColumn('description', fn($row) => $row->description)
            ->addColumn('amount', fn($row) => $row->amount)
            ->addColumn('transaction_date', fn($row) => $row->transaction_date)
            ->addColumn('custody_id', fn($row) => $row->custody_id)
            ->addColumn('custody', fn($row) => $row->custody)
            ->toJson();
    }

    public function myCustodies()
    {
        $user = auth()->user();

        // T28: متاحة لكل مستخدم مسجل (كانت حكراً على المندوبين) — الاستلام الخارجي الذاتي يمنح الجميع عهدات
        // Get the user's own custodies with related data
        $myCustodies = Custody::where('agent_id', $user->id)
            ->with(['treasury', 'accountant', 'transactions', 'expenses'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get transfers sent by this agent (pending + approved)
        $sentTransfers = \App\Models\CustodyTransfer::where('from_agent_id', $user->id)
            ->with(['toAgent', 'custody.treasury'])
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get transfers received by this agent (pending for action + approved history
        // — approved ones are credited to an existing custody via transferred_in,
        // so they must stay visible here or the money appears nowhere)
        $receivedTransfers = \App\Models\CustodyTransfer::where('to_agent_id', $user->id)
            ->with(['fromAgent', 'custody.treasury'])
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        // For financial calculations, exclude rejected and pending custodies
        $acceptedCustodies = $myCustodies->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed']);

        $stats = [
            'total_custodies' => $myCustodies->count(),
            'active_custodies' => $myCustodies->whereIn('status', ['accepted', 'active'])->count(),
            'pending_custodies' => $myCustodies->where('status', 'pending')->count(),
            'pending_transfers_sent' => $sentTransfers->where('status', 'pending')->count(),
            'pending_transfers_received' => $receivedTransfers->where('status', 'pending')->count(),
            // Financial stats only for accepted custodies (exclude rejected and pending)
            'total_amount' => $acceptedCustodies->sum('amount'),
            'total_spent' => $acceptedCustodies->sum('spent'),
            'total_returned' => $acceptedCustodies->sum('returned'),
            // التحويلات جزء من رصيد العهدة ولا تظهر ضمن amount — تُعرض منفصلة حتى يعرف صاحبها مصدر كل جنيه
            'total_transferred_in' => $acceptedCustodies->sum('transferred_in'),
            'total_transferred_out' => $acceptedCustodies->sum('transferred_out'),
            'total_remaining' => $acceptedCustodies->sum(fn($c) => $c->getRemainingBalance()),
        ];

        // Get all treasuries for refund modal
        $treasuries = Treasury::all();

        return view('custodies.my-custodies', compact(
            'myCustodies',
            'sentTransfers',
            'receivedTransfers',
            'stats',
            'treasuries'
        ));
    }

    public function allCustodies()
    {
        // Accountants, managers, and viewers (مشرف) can see all custodies
        $user = auth()->user();
        if (!$user->can('approve_custody') && !$user->can('view_all_records')) {
            abort(403, 'Unauthorized');
        }

        // Get all custodies with related data
        $custodies = Custody::with(['agent', 'treasury', 'accountant', 'transactions', 'expenses'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        // For financial calculations, exclude rejected and pending custodies
        $acceptedCustodies = $custodies->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed']);

        // Breakdown by status
        $activeCustodies = $custodies->whereIn('status', ['accepted', 'active']);
        $pendingCustodies = $custodies->where('status', 'pending');
        $rejectedCustodies = $custodies->where('status', 'rejected');
        $partiallyReturnedCustodies = $custodies->where('status', 'partially_returned');
        $closedCustodies = $custodies->where('status', 'closed');

        $stats = [
            'total_custodies' => $custodies->count(),
            'active_custodies' => $activeCustodies->count(),
            'pending_custodies' => $pendingCustodies->count(),
            'rejected_custodies' => $rejectedCustodies->count(),
            'closed_custodies' => $closedCustodies->count(),
            // Financial stats only for accepted custodies (exclude rejected and pending)
            'total_amount' => $acceptedCustodies->sum('amount'),
            'total_spent' => $acceptedCustodies->sum('spent'),
            'total_returned' => $acceptedCustodies->sum('returned'),
            'total_transferred_in' => $acceptedCustodies->sum('transferred_in'),
            'total_transferred_out' => $acceptedCustodies->sum('transferred_out'),
            'total_remaining' => $acceptedCustodies->sum(fn($c) => $c->getRemainingBalance()),
            'pending_returns' => $acceptedCustodies->sum('pending_return'),
            // Breakdown by status with amounts
            'active_amount' => $activeCustodies->sum('amount'),
            'active_spent' => $activeCustodies->sum('spent'),
            'active_remaining' => $activeCustodies->sum(fn($c) => $c->getRemainingBalance()),
            'pending_amount' => $pendingCustodies->sum('amount'),
            'rejected_amount' => $rejectedCustodies->sum('amount'),
            'rejected_spent' => $rejectedCustodies->sum('spent'),
            'partially_returned_amount' => $partiallyReturnedCustodies->sum('amount'),
            'partially_returned_spent' => $partiallyReturnedCustodies->sum('spent'),
            'partially_returned_returned' => $partiallyReturnedCustodies->sum('returned'),
        ];

        // Get agents list for filtering
        $agents = User::role('مندوب')->orderBy('name')->get();

        // Build per-agent summary for breakdown view
        // Filter out custodies with deleted agents (null agent relationship)
        $custodiesWithAgents = $activeCustodies->filter(fn($c) => $c->agent !== null);

        $agentsSummary = $custodiesWithAgents
            ->groupBy('agent_id')
            ->map(function ($agentCustodies) {
                $agent = $agentCustodies->first()->agent;
                return [
                    'agent' => $agent,
                    'count' => $agentCustodies->count(),
                    'total_amount' => $agentCustodies->sum('amount'),
                    'total_transferred_in' => $agentCustodies->sum('transferred_in'),
                    'total_transferred_out' => $agentCustodies->sum('transferred_out'),
                    'total_spent' => $agentCustodies->sum('spent'),
                    'total_returned' => $agentCustodies->sum('returned'),
                    'total_remaining' => $agentCustodies->sum(fn($c) => $c->getRemainingBalance()),
                    'custodies' => $agentCustodies->map(fn($c) => [
                        'id' => $c->id,
                        'amount' => $c->amount,
                        'spent' => $c->spent,
                        'transferred_in' => $c->transferred_in,
                        'transferred_out' => $c->transferred_out,
                        'returned' => $c->returned,
                        'remaining' => $c->getRemainingBalance(),
                        'status' => $c->status,
                        'created_at' => $c->created_at->format('Y-m-d'),
                    ])->values(),
                ];
            })
            ->sortByDesc('total_remaining')
            ->values();

        return view('custodies.all-custodies', compact('custodies', 'stats', 'agents', 'agentsSummary'));
    }

    /**
     * T28: استلام خارجي ذاتي — متاح لكل مستخدم مسجل أياً كان دوره، بلا موافقة وبلا حد أقصى
     */
    public function storeExternalReceipt(Request $request)
    {
        // T28 rev2: المشرف دور اطلاعي فقط — لا يستلم خارجياً ولا تحدث له عهدة
        if (auth()->user()->hasRole('مشرف')) {
            return $this->formBackOrJson($request, 'error', 'الاستلام الخارجي غير متاح لدور المشرف. المشرف دور للاطلاع فقط دون استلام أو صرف أموال');
        }

        // نفس قواعد ورسائل إضافة التبرع في الخزينة (بلا selector خزينة وبلا حد أقصى للمبلغ)
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'external_source' => 'required|string|max:255',
            'description' => 'required|string|max:500',
        ]);

        try {
            $custody = $this->service->recordExternalReceipt(
                auth()->id(),
                $validated['amount'],
                $validated['external_source'],
                $validated['description']
            );

            ActivityLogService::created(
                $custody,
                'استلام خارجي بمبلغ ' . number_format($validated['amount'], 2) . ' ج.م من الجهة: ' . $validated['external_source']
            );

            // إشعار المديرين والمحاسبين مرة واحدة لكل مستخدم (طريقة T21)
            $user = auth()->user();
            $notificationMessage = 'المستخدم: ' . $user->name . ' - سجل استلاماً خارجياً بمبلغ '
                . number_format($validated['amount'], 2) . ' ج.م من الجهة: ' . $validated['external_source']
                . ' - الوصف: ' . $validated['description'];
            NotificationService::notifyByRoles(['مدير', 'محاسب'], 'استلام خارجي جديد', $notificationMessage, 'info', $custody->id, 'custody');

            return $this->formBackOrJson($request, 'success', 'تم تسجيل الاستلام الخارجي وإضافة عهدة جديدة لك بنجاح');
        } catch (\Exception $e) {
            return $this->formBackOrJson($request, 'error', 'حدث خطأ أثناء تسجيل الاستلام الخارجي: ' . $e->getMessage(), true);
        }
    }

    /**
     * API endpoint for user's custodies with available balance
     */
    public function userCustodiesApi()
    {
        $custodies = Custody::where('agent_id', auth()->id())
            ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
            ->get()
            ->map(function ($custody) {
                $remaining = $custody->getRemainingBalance();
                if ($remaining > 0) {
                    return [
                        'id' => $custody->id,
                        'reason' => $custody->notes ?: 'عهدة #' . $custody->id,
                        'amount' => (float)$custody->amount,
                        'spent' => (float)$custody->spent,
                        'returned' => (float)$custody->returned,
                        'pending_return' => (float)$custody->pending_return,
                        'pending_transfer_out' => (float)($custody->pending_transfer_out ?? 0),
                        'transferred_in' => (float)($custody->transferred_in ?? 0),
                        'transferred_out' => (float)($custody->transferred_out ?? 0),
                        'remaining' => (float)$remaining,
                        // T27: إجمالي المتاح لو اختار المستخدم هذه العهدة (هي أولاً ثم بقية عهداته)
                        'total_available' => app(\App\Services\TreasuryService::class)->totalAvailableForCustody($custody->id),
                    ];
                }
                return null;
            })
            ->filter()
            ->values();

        return response()->json($custodies);
    }

    /**
     * Cancel a pending custody (accountant/manager initiated only)
     */
    public function cancel(Request $request, Custody $custody)
    {
        $this->authorize('approve_custody');

        // Only allow cancelling pending custodies that were initiated by accountant/manager
        if ($custody->status !== 'pending' || $custody->initiated_by !== 'accountant') {
            return redirect()->route('custodies.show', $custody->id)
                ->with('error', 'لا يمكن إلغاء هذه العهدة في الوقت الحالي.');
        }

        $validated = $request->validate([
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Get the agent who was supposed to receive the custody
            $agent = $custody->agent;

            // Delete any pending notifications for this custody
            Notification::where('notifiable_id', $agent->id)
                ->where('notifiable_type', User::class)
                ->where('data', 'like', '%"custody_id":' . $custody->id . '%')
                ->delete();

            // Create activity log
            $cancelReason = $validated['cancel_reason'] ?? 'بدون سبب محدد';
            ActivityLogService::log(
                auth()->user()->id,
                'cancel_custody',
                'custodies',
                $custody->id,
                'تم إلغاء العهدة: ' . $cancelReason
            );

            // Change status to cancelled (or delete)
            $custody->status = 'cancelled';
            $custody->save();

            DB::commit();

            return redirect()->route('custodies.show', $custody->id)
                ->with('success', 'تم إلغاء العهدة بنجاح والإشعارات المرسلة للمندوب.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('custodies.show', $custody->id)
                ->with('error', 'حدث خطأ أثناء إلغاء العهدة: ' . $e->getMessage());
        }
    }

    /**
     * API endpoint for DataTables server-side processing (used by modern.blade.php)
     */
}
