<?php

namespace App\Http\Controllers;

use App\Models\CustodyTransfer;
use App\Models\Custody;
use App\Services\CustodyTransferService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

class CustodyTransferController extends Controller
{
    public function __construct(private CustodyTransferService $service) {}

    /**
     * Display a listing of custody transfers
     */
    public function index()
    {
        $this->authorize('transfer_custody');

        $user = auth()->user();

        // Get statistics for current user
        $sentTransfersCount = $user->transfersSent()->count();
        $receivedTransfersCount = $user->transfersReceived()->count();
        $pendingTransfersCount = $user->transfersReceived()->pending()->count();

        return view('custody-transfers.modern', compact(
            'sentTransfersCount',
            'receivedTransfersCount',
            'pendingTransfersCount'
        ));
    }

    /**
     * Show the form for creating a new custody transfer
     */
    public function create()
    {
        $this->authorize('transfer_custody');

        $user = auth()->user();

        // Get custodies for current agent that are in accepted status
        $custodies = Custody::where('agent_id', $user->id)
            ->where('status', 'accepted')
            ->with('agent', 'treasury')
            ->get()
            ->map(function ($custody) {
                $custody->remaining_balance = $custody->getRemainingBalance();
                return $custody;
            });

        // Get other agents
        $agents = \App\Models\User::whereHas('roles', function ($q) {
            $q->where('name', 'مندوب');
        })
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->get();

        return view('custody-transfers.modern-create', compact('custodies', 'agents'));
    }

    /**
     * Store a newly created custody transfer
     */
    public function store(Request $request)
    {
        $this->authorize('transfer_custody');

        $request->validate([
            'custody_id' => 'required|exists:custodies,id',
            'to_agent_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->service->createTransferRequest(
                auth()->id(),
                $request->to_agent_id,
                $request->custody_id,
                $request->amount,
                $request->notes
            );

            return redirect()->route('custody-transfers.index')
                ->with('success', 'تم إنشاء طلب التحويل بنجاح');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified custody transfer
     */
    public function show(CustodyTransfer $custodyTransfer)
    {
        $this->authorize('view', $custodyTransfer);

        $custodyTransfer->load(['fromAgent', 'toAgent', 'custody', 'approver']);

        return view('custody-transfers.modern-show', compact('custodyTransfer'));
    }

    /**
     * Approve the custody transfer
     */
    public function approve(Request $request, CustodyTransfer $custodyTransfer)
    {
        $this->authorize('approve_custody_transfer');

        if ($custodyTransfer->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'يمكن فقط الموافقة على الطلبات المعلقة');
        }

        try {
            $this->service->approveTransfer($custodyTransfer, auth()->id());

            return redirect()->route('custody-transfers.show', $custodyTransfer)
                ->with('success', 'تم قبول التحويل بنجاح');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Reject the custody transfer
     */
    public function reject(Request $request, CustodyTransfer $custodyTransfer)
    {
        $this->authorize('approve_custody_transfer');

        if ($custodyTransfer->status !== 'pending') {
            return redirect()->back()
                ->with('error', 'يمكن فقط رفض الطلبات المعلقة');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $this->service->rejectTransfer(
                $custodyTransfer,
                auth()->id(),
                $request->rejection_reason
            );

            return redirect()->route('custody-transfers.show', $custodyTransfer)
                ->with('success', 'تم رفض التحويل');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get DataTable data for sent transfers
     */
    public function sentTransfersData()
    {
        $this->authorize('transfer_custody');

        $user = auth()->user();
        $transfers = CustodyTransfer::with(['toAgent', 'custody'])
            ->where('from_agent_id', $user->id)
            ->get();

        return DataTables::of($transfers)
            ->addColumn('to_agent_name', fn($row) => $row->toAgent->name)
            ->addColumn('status_badge', fn($row) => $this->getStatusBadge($row->status))
            ->addColumn('action', fn($row) => view('custody-transfers.partials.action-buttons', ['transfer' => $row])->render())
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    /**
     * Get DataTable data for received transfers
     */
    public function receivedTransfersData()
    {
        $this->authorize('transfer_custody');

        $user = auth()->user();
        $transfers = CustodyTransfer::with(['fromAgent', 'custody'])
            ->where('to_agent_id', $user->id)
            ->get();

        return DataTables::of($transfers)
            ->addColumn('from_agent_name', fn($row) => $row->fromAgent->name)
            ->addColumn('status_badge', fn($row) => $this->getStatusBadge($row->status))
            ->addColumn('action', fn($row) => view('custody-transfers.partials.action-buttons', ['transfer' => $row])->render())
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    /**
     * Get status badge HTML
     */
    private function getStatusBadge($status)
    {
        $badges = [
            'pending' => '<span class="badge bg-warning">قيد الانتظار</span>',
            'approved' => '<span class="badge bg-success">تم القبول</span>',
            'rejected' => '<span class="badge bg-danger">تم الرفض</span>',
        ];

        return $badges[$status] ?? '<span class="badge bg-secondary">غير معروف</span>';
    }
}
