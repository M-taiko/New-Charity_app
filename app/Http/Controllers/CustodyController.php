<?php

namespace App\Http\Controllers;

use App\Models\Custody;
use App\Models\Treasury;
use App\Models\User;
use App\Services\TreasuryService;
use App\Services\ActivityLogService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

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

        // Get all treasuries for selection
        $treasuries = Treasury::all();

        if ($treasuries->isEmpty()) {
            return redirect()->route('custodies.index')->with('error', 'لم يتم العثور على خزائن. يرجى الاتصال بالمسؤول.');
        }

        // Route to appropriate view based on request type
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
            // Agent request (agent requests custody for themselves)
            return view('custodies.agent-request', compact('treasuries'));
        }
    }

    public function store(Request $request)
    {
        $isAgent = auth()->user()->hasRole('مندوب');
        $isForSelf = $request->has('for_self'); // Accountant/Manager requesting for themselves

        if (!$isAgent && !$isForSelf) {
            $this->authorize('create_custody');
        }

        // Get selected treasury or default to first
        $treasuryId = $request->input('treasury_id');
        if ($treasuryId) {
            $treasury = Treasury::findOrFail($treasuryId);
        } else {
            $treasury = Treasury::first();
            if (!$treasury) {
                return back()->with('error', 'لم يتم العثور على خزينة. يرجى الاتصال بالمسؤول.');
            }
        }

        // Build validation rules with conditional treasury balance check for agents
        $validationRules = [
            'agent_id' => ($isAgent || $isForSelf) ? 'nullable' : 'required|exists:users,id',
            'treasury_id' => 'required|exists:treasuries,id',
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:1000000', // Reasonable maximum
            ],
            'issued_date' => 'required|date',
            'notes' => 'nullable|string',
        ];

        // Add max amount rule (don't exceed selected treasury balance)
        $validationRules['amount'][] = 'max:' . $treasury->balance;

        // Customize error message
        $amountMaxError = 'المبلغ المدخل يتجاوز رصيد الخزينة المختارة. الحد الأقصى: ' . number_format($treasury->balance, 2) . ' ج.م';

        $request->validate(
            $validationRules,
            [
                'amount.max' => $amountMaxError,
            ]
        );

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

        $this->service->createCustody(
            $treasury->id,
            $agentId,
            auth()->id(),
            $request->amount,
            $request->notes,
            $isAgentRequest
        );

        $message = $isAgentRequest ? 'تم إرسال طلب العهدة للمحاسب للموافقة' : 'تم إنشاء العهدة بنجاح';
        ActivityLogService::log('created', ($isAgentRequest ? 'طلب عهدة جديد' : 'إنشاء عهدة') . ' بمبلغ ' . number_format($request->amount, 2) . ' ج.م');
        return redirect()->route($isAgentRequest ? 'agent.transactions' : 'custodies.index')->with('success', $message);
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

        // Get treasury amounts distribution
        $treasuryAmounts = $request->input('treasury_amounts', []);

        if (empty($treasuryAmounts) || !array_filter($treasuryAmounts)) {
            return back()->with('error', 'يرجى توزيع المبالغ على الخزائن');
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
                    return back()->with('error', "رصيد خزينة '{$treasury->name}' غير كافي. المطلوب: {$amount}, المتاح: {$treasury->balance}");
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
            return back()->with('error', 'مجموع المبالغ المتوزعة يجب أن يساوي ' . number_format($custody->amount, 2) . ' ج.م');
        }

        try {
            $this->service->acceptCustodyWithDistribution($custody, $distribution);
            ActivityLogService::approved($custody, 'تم الموافقة على العهدة #' . $custody->id . ' للمندوب ' . $custody->agent->name);
            return back()->with('success', 'تم الموافقة على العهدة وتوزيع الأموال بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
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
            return back()->with('success', 'تم رفض العهدة');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function agentAccept(Custody $custody, Request $request)
    {
        // Get selected treasury
        $treasuryId = $request->input('treasury_id');
        if (!$treasuryId) {
            return back()->with('error', 'يرجى اختيار خزينة');
        }

        $treasury = Treasury::findOrFail($treasuryId);

        // Validate that treasury has enough balance
        if ($treasury->balance < $custody->amount) {
            return back()->with('error', 'رصيد الخزينة المختارة غير كافي. الرصيد المتاح: ' . number_format($treasury->balance, 2) . ' ج.م');
        }

        try {
            $this->service->agentAcceptCustodyFromTreasury($custody, $treasury);
            return back()->with('success', 'تم قبول العهدة وصرف الفلوس بنجاح');
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
            return back()->with('success', 'تم رفض العهدة');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function return(Custody $custody, Request $request)
    {
        // Only the agent who owns this custody can request to return it
        if ($custody->agent_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $remainingBalance = $custody->getRemainingBalance();

        $request->validate([
            'returned_amount' => 'required|numeric|min:0.01|max:' . $remainingBalance,
        ]);

        $this->service->requestReturnCustody($custody, $request->returned_amount);
        ActivityLogService::returned($custody, 'طلب رد ' . number_format($request->returned_amount, 2) . ' ج.م من العهدة #' . $custody->id);
        return back()->with('success', 'تم إرسال طلب رد العهدة للمحاسب');
    }

    public function addExternalDonation(Request $request, Custody $custody)
    {
        $this->authorize('manage_treasury');

        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'type'        => 'required|in:external_donation,expense_refund',
        ]);

        try {
            $this->service->addExternalDonationToCustody($custody, $request->amount, $request->description, $request->type);
            return back()->with('success', 'تم إضافة المبلغ لرصيد العهدة بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approveReturn(Custody $custody)
    {
        $this->authorize('approve_custody');

        if ($custody->pending_return <= 0) {
            return back()->with('error', 'لا يوجد مبلغ معلق للموافقة عليه');
        }

        $pendingAmount = $custody->pending_return;
        $this->service->approveCustodyReturn($custody);
        ActivityLogService::approved($custody, 'تم قبول رد ' . number_format($pendingAmount, 2) . ' ج.م من العهدة #' . $custody->id . ' للمندوب ' . $custody->agent->name);
        return back()->with('success', 'تم قبول رد العهدة والتحويل للخزينة');
    }

    public function directReturn(Request $request, Custody $custody)
    {
        $this->authorize('approve_custody');

        $remaining = $custody->getRemainingBalance();

        $request->validate([
            'return_amount' => 'required|numeric|min:0.01|max:' . $remaining,
        ], [
            'return_amount.max' => 'المبلغ يتجاوز الرصيد المتاح (' . number_format($remaining, 2) . ' ج.م)',
        ]);

        try {
            $this->service->returnCustody($custody, $request->return_amount);
            ActivityLogService::returned($custody, 'رد مباشر ' . number_format($request->return_amount, 2) . ' ج.م من العهدة #' . $custody->id . ' للخزينة');
            return back()->with('success', 'تم رد المبلغ للخزينة مباشرة بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function tableData()
    {
        $this->authorize('manage_treasury');
        $custodies = Custody::with(['agent', 'accountant'])->get();

        return DataTables::of($custodies)
            ->addColumn('agent_name', fn($row) => $row->agent->name)
            ->addColumn('spent_percent', fn($row) => $row->amount > 0 ? round(($row->spent / $row->amount) * 100) . '%' : '0%')
            ->addColumn('remaining', fn($row) => $row->getRemainingBalance())
            ->addColumn('status_label', fn($row) => $this->getStatusLabel($row->status))
            ->addColumn('actions', fn($row) => view('custodies.actions', compact('row'))->render())
            ->rawColumns(['status_label', 'actions'])
            ->toJson();
    }

    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => '<span class="badge bg-warning">قيد الانتظار</span>',
            'accepted' => '<span class="badge bg-success">مقبول</span>',
            'rejected' => '<span class="badge bg-danger">مرفوض</span>',
            'partially_returned' => '<span class="badge bg-info">مرتجع جزئياً</span>',
            'closed' => '<span class="badge bg-secondary">مغلق</span>',
        ];
        return $labels[$status] ?? '';
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
        $totalReceived = $activeCustodies->sum('amount');
        $totalSpent    = $activeCustodies->sum('spent');
        $totalReturned = $activeCustodies->sum('returned');

        return view('custodies.agent-transactions', compact('custodies', 'custodiesCount', 'totalReceived', 'totalSpent', 'totalReturned'));
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

        // Check if user is agent (مندوب)
        if (!$user->hasRole('مندوب')) {
            abort(403, 'Unauthorized');
        }

        // Get agent's custodies with related data
        $custodies = Custody::where('agent_id', $user->id)
            ->with(['treasury', 'accountant', 'transactions', 'expenses'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate statistics
        // For financial calculations, exclude rejected and pending custodies
        $acceptedCustodies = $custodies->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed']);

        $stats = [
            'total_custodies' => $custodies->count(),
            'active_custodies' => $custodies->whereIn('status', ['accepted', 'active'])->count(),
            'pending_custodies' => $custodies->where('status', 'pending')->count(),
            // Financial stats only for accepted custodies (exclude rejected and pending)
            'total_amount' => $acceptedCustodies->sum('amount'),
            'total_spent' => $acceptedCustodies->sum('spent'),
            'total_returned' => $acceptedCustodies->sum('returned'),
            'total_remaining' => $acceptedCustodies->sum(fn($c) => $c->getRemainingBalance()),
        ];

        return view('custodies.my-custodies', compact('custodies', 'stats'));
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

        $stats = [
            'total_custodies' => $custodies->count(),
            'active_custodies' => $custodies->whereIn('status', ['accepted', 'active'])->count(),
            'pending_custodies' => $custodies->where('status', 'pending')->count(),
            'rejected_custodies' => $custodies->where('status', 'rejected')->count(),
            'closed_custodies' => $custodies->where('status', 'closed')->count(),
            // Financial stats only for accepted custodies (exclude rejected and pending)
            'total_amount' => $acceptedCustodies->sum('amount'),
            'total_spent' => $acceptedCustodies->sum('spent'),
            'total_returned' => $acceptedCustodies->sum('returned'),
            'total_remaining' => $acceptedCustodies->sum(fn($c) => $c->getRemainingBalance()),
            'pending_returns' => $acceptedCustodies->sum('pending_return'),
        ];

        // Get agents list for filtering
        $agents = User::role('مندوب')->orderBy('name')->get();

        return view('custodies.all-custodies', compact('custodies', 'stats', 'agents'));
    }
}
