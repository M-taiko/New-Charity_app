<?php

namespace App\Http\Controllers;

use App\Models\Custody;
use App\Models\CustodyReturnRequest;
use Illuminate\Http\Request;

class CustodyReturnRequestController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Get custody IDs where user is the owner
        $myCustodyIds = Custody::where(function ($query) use ($user) {
            // User is agent and initiated as agent
            $query->where('agent_id', $user->id)
                  ->where('initiated_by', 'agent')
                  // OR user is accountant and initiated as accountant
                  ->orWhere(function ($q) use ($user) {
                      $q->where('accountant_id', $user->id)
                        ->where('initiated_by', 'accountant');
                  });
        })->pluck('id');

        // Get requests only for user's custodies
        $pendingRequests = CustodyReturnRequest::with(['custody', 'requester'])
            ->whereIn('custody_id', $myCustodyIds)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        $approvedRequests = CustodyReturnRequest::with(['custody', 'requester', 'approver'])
            ->whereIn('custody_id', $myCustodyIds)
            ->where('status', 'approved')
            ->orderBy('approved_at', 'desc')
            ->take(50)
            ->get();

        $rejectedRequests = CustodyReturnRequest::with(['custody', 'requester', 'approver'])
            ->whereIn('custody_id', $myCustodyIds)
            ->where('status', 'rejected')
            ->orderBy('approved_at', 'desc')
            ->take(50)
            ->get();

        $stats = [
            'pending_count' => $pendingRequests->count(),
            'approved_count' => CustodyReturnRequest::whereIn('custody_id', $myCustodyIds)->where('status', 'approved')->count(),
            'rejected_count' => CustodyReturnRequest::whereIn('custody_id', $myCustodyIds)->where('status', 'rejected')->count(),
            'total_pending_amount' => $pendingRequests->sum('amount'),
        ];

        return view('custody-return-requests.index', compact(
            'pendingRequests',
            'approvedRequests',
            'rejectedRequests',
            'stats'
        ));
    }
}
