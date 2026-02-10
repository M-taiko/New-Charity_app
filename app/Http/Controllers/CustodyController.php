<?php

namespace App\Http\Controllers;

use App\Models\Custody;
use App\Models\Treasury;
use App\Models\User;
use App\Services\TreasuryService;
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

    public function create()
    {
        $isAgent = auth()->user()->hasRole('مندوب');

        // Agents can request custody for themselves
        // Accountants and managers can create custody for agents
        if (!$isAgent) {
            $this->authorize('create_custody');
        }

        $agents = User::role('مندوب')->get();
        $treasury = Treasury::first();

        if (!$treasury) {
            return redirect()->route('custodies.index')->with('error', 'لم يتم العثور على خزينة. يرجى الاتصال بالمسؤول.');
        }

        return view('custodies.modern-create', compact('agents', 'treasury', 'isAgent'));
    }

    public function store(Request $request)
    {
        $isAgent = auth()->user()->hasRole('مندوب');

        if (!$isAgent) {
            $this->authorize('create_custody');
        }

        $treasury = Treasury::first();
        if (!$treasury) {
            return back()->with('error', 'لم يتم العثور على خزينة. يرجى الاتصال بالمسؤول.');
        }

        // Build validation rules with conditional treasury balance check for agents
        $validationRules = [
            'agent_id' => $isAgent ? 'nullable' : 'required|exists:users,id',
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],
            'issued_date' => 'required|date',
            'notes' => 'nullable|string',
        ];

        // Add max amount rule for agents requesting custody
        if ($isAgent) {
            $validationRules['amount'][] = 'max:' . $treasury->balance;
        }

        $request->validate(
            $validationRules,
            [
                'amount.max' => 'المبلغ المطلوب (' . $request->amount . ' ج.م) يتجاوز الرصيد المتاح في الخزينة (' . number_format($treasury->balance, 2) . ' ج.م)',
            ]
        );

        // If agent, use their own ID; otherwise use the selected agent_id
        $agentId = $isAgent ? auth()->id() : $request->agent_id;

        $this->service->createCustody(
            $treasury->id,
            $agentId,
            auth()->id(),
            $request->amount,
            $request->notes,
            $isAgentRequest = $isAgent
        );

        $message = $isAgent ? 'تم إرسال طلب العهدة للمحاسب للموافقة' : 'تم إنشاء العهدة بنجاح';
        return redirect()->route($isAgent ? 'agent.transactions' : 'custodies.index')->with('success', $message);
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
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string',
        ]);

        $custody->update($request->only(['amount', 'notes']));

        return redirect()->route('custodies.index')->with('success', 'تم تحديث العهدة بنجاح');
    }

    public function accept(Custody $custody)
    {
        $this->authorize('approve_custody');

        try {
            $this->service->acceptCustody($custody);
            return back()->with('success', 'تم الموافقة على العهدة');
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
            return back()->with('success', 'تم رفض العهدة');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function agentAccept(Custody $custody)
    {
        try {
            $this->service->agentAcceptCustody($custody);
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
            'returned_amount' => 'required|numeric|min:1|max:' . $remainingBalance,
        ]);

        $this->service->requestReturnCustody($custody, $request->returned_amount);
        return back()->with('success', 'تم إرسال طلب رد العهدة للمحاسب');
    }

    public function approveReturn(Custody $custody)
    {
        $this->authorize('approve_custody');

        if ($custody->pending_return <= 0) {
            return back()->with('error', 'لا يوجد مبلغ معلق للموافقة عليه');
        }

        $this->service->approveCustodyReturn($custody);
        return back()->with('success', 'تم قبول رد العهدة والتحويل للخزينة');
    }

    public function tableData()
    {
        $this->authorize('manage_treasury');
        $custodies = Custody::with(['agent', 'accountant'])->get();

        return DataTables::of($custodies)
            ->addColumn('agent_name', fn($row) => $row->agent->name)
            ->addColumn('spent_percent', fn($row) => round(($row->spent / $row->amount) * 100) . '%')
            ->addColumn('remaining', fn($row) => number_format($row->getRemainingBalance(), 2))
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

        // Get agent's custodies
        $custodies = Custody::where('agent_id', $user->id)->get();
        $custodiesCount = $custodies->count();

        // Calculate totals
        $totalReceived = $custodies->sum('amount');
        $totalSpent = $custodies->sum('spent');
        $totalReturned = $custodies->sum('returned');

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
        $stats = [
            'total_custodies' => $custodies->count(),
            'active_custodies' => $custodies->whereIn('status', ['accepted', 'active'])->count(),
            'pending_custodies' => $custodies->where('status', 'pending')->count(),
            'total_amount' => $custodies->sum('amount'),
            'total_spent' => $custodies->sum('spent'),
            'total_returned' => $custodies->sum('returned'),
            'total_remaining' => $custodies->sum(fn($c) => $c->getRemainingBalance()),
        ];

        return view('custodies.my-custodies', compact('custodies', 'stats'));
    }
}
