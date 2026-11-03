<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Custody;
use App\Models\CustodyReturnRequest;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use App\Support\Api\ApiList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustodyController extends Controller
{
    public function __construct(private TreasuryService $service) {}

    /** GET /api/v1/custodies — management list (T9 cards add up to the total) */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $paginator = ApiList::paginate(
            Custody::with(['agent', 'accountant', 'treasury']),
            $request,
            sortable: ['amount' => 'amount', 'created_at' => 'created_at', 'date' => 'created_at', 'agent' => 'agent_id', 'transferred_in' => 'transferred_in', 'spent' => 'spent', 'status' => 'status'],
            search: fn ($q, $term) => $q->whereHas('agent', fn ($a) => $a->where('name', 'like', "%{$term}%"))
                ->orWhere('notes', 'like', "%{$term}%"),
            filters: [
                'status' => fn ($q, $v) => $q->where('status', $v),
                'agent_id' => fn ($q, $v) => $q->where('agent_id', (int) $v),
            ],
        );

        // list contract: agent/accountant/treasury are NAME strings, not the
        // nested models raw serialization would emit ([object Object] in the SPA)
        $paginator->getCollection()->transform(function (Custody $c) {
            $row = $c->toArray();
            $row['agent'] = $c->agent?->name;
            $row['accountant'] = $c->accountant?->name;
            $row['treasury'] = $c->treasury?->name;
            return $row;
        });

        return response()->json(ApiList::envelope($paginator, fn () => [
            'accepted' => Custody::whereIn('status', ['accepted', 'active'])->count(),
            'pending' => Custody::where('status', 'pending')->count(),
            'rejected' => Custody::where('status', 'rejected')->count(),
            'closed_or_cancelled' => Custody::whereIn('status', ['closed', 'cancelled'])->count(),
            'total' => Custody::count(),
        ]));
    }

    /** GET /api/v1/custodies/{id} — full detail payload for the custody page */
    public function show(Request $request, Custody $custody): JsonResponse
    {
        // IDOR: custody details only for record viewers/treasury managers or its agent
        $u = $request->user();
        abort_unless(
            $u->can('view_all_records') || $u->can('manage_treasury') || $custody->agent_id === $u->id,
            403,
            'غير مصرح لك بعرض هذه العهدة'
        );

        $custody->load(['agent', 'accountant', 'treasury', 'expenses' => fn ($q) => $q->latest(), 'expenses.category', 'expenses.item']);

        $transfers = \App\Models\CustodyTransfer::where('custody_id', $custody->id)
            ->with(['fromAgent', 'toAgent', 'decidedBy'])
            ->latest()
            ->get();

        $returnRequests = CustodyReturnRequest::where('custody_id', $custody->id)
            ->with(['requester', 'approver'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $this->detail($custody) + [
                'expenses' => $custody->expenses->map(fn ($e) => [
                    'id' => $e->id,
                    'amount' => number_format((float) $e->amount, 2, '.', ''),
                    'type' => $e->type,
                    'description' => $e->description,
                    'date' => $e->expense_date?->toIso8601ZuluString(),
                    'is_quick' => (bool) $e->is_quick_expense,
                    'reviewed' => $e->reviewed_at !== null,
                    'direction' => $e->accounting_path,
                    'split' => $e->custodies->map(fn ($c) => [
                        'id' => $c->id,
                        'amount' => number_format((float) $c->pivot->amount, 2, '.', ''),
                    ]),
                ]),
                'transfers' => $transfers->map(fn ($t) => [
                    'id' => $t->id,
                    'sender' => $t->fromAgent?->name,
                    'receiver' => $t->toAgent?->name,
                    'amount' => number_format((float) $t->amount, 2, '.', ''),
                    'status' => $t->status,
                    'requested_at' => $t->created_at?->toIso8601ZuluString(),
                    'decided_at' => $t->decision_date?->toIso8601ZuluString() ?? $t->updated_at?->toIso8601ZuluString(),
                    'decided_by' => $t->decidedBy?->name,
                    'rejection_reason' => $t->rejection_reason,
                ]),
                'return_requests' => $returnRequests->map(fn ($r) => [
                    'id' => $r->id,
                    'amount' => number_format((float) $r->amount, 2, '.', ''),
                    'reason' => $r->reason,
                    'status' => $r->status,
                    'requested_by' => $r->requester?->name,
                    'requested_at' => $r->created_at?->toIso8601ZuluString(),
                    'approved_by' => $r->approver?->name,
                    'approved_at' => $r->approved_at?->toIso8601ZuluString(),
                    'rejection_reason' => $r->status === 'rejected' ? $r->approval_notes : null,
                ]),
                'transactions' => $custody->transactions()
                    ->with('user')->latest('transaction_date')->limit(100)->get()
                    ->map(fn ($t) => $this->transactionRow($t)),
            ],
        ]);
    }

    /** GET /api/v1/custodies/mine — my custodies page (open to every user, round 3) */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        $myCustodies = Custody::where('agent_id', $user->id)
            ->with(['treasury', 'accountant'])
            ->orderByDesc('created_at')
            ->get();

        $accepted = $myCustodies->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed']);

        $sentTransfers = \App\Models\CustodyTransfer::where('from_agent_id', $user->id)
            ->with(['toAgent', 'custody'])
            ->whereIn('status', ['pending', 'approved'])->latest()->get();
        $receivedTransfers = \App\Models\CustodyTransfer::where('to_agent_id', $user->id)
            ->with(['fromAgent', 'custody'])
            ->whereIn('status', ['pending', 'approved'])->latest()->get();

        return response()->json([
            'data' => [
                'stats' => [
                    'total_custodies' => $myCustodies->count(),
                    'active_custodies' => $myCustodies->whereIn('status', ['accepted', 'active'])->count(),
                    'pending_custodies' => $myCustodies->where('status', 'pending')->count(),
                    'total_amount' => number_format((float) $accepted->sum('amount'), 2, '.', ''),
                    'total_spent' => number_format((float) $accepted->sum('spent'), 2, '.', ''),
                    'total_returned' => number_format((float) $accepted->sum('returned'), 2, '.', ''),
                    'total_transferred_in' => number_format((float) $accepted->sum('transferred_in'), 2, '.', ''),
                    'total_transferred_out' => number_format((float) $accepted->sum('transferred_out'), 2, '.', ''),
                    // T26: المجمّد يظهر فقط عندما يكون أكبر من صفر
                    'frozen' => number_format((float) $accepted->sum('pending_transfer_out'), 2, '.', ''),
                    'total_remaining' => number_format((float) $accepted->sum(fn ($c) => $c->getRemainingBalance()), 2, '.', ''),
                ],
                'custodies' => $myCustodies->map(fn ($c) => $this->row($c)),
                'sent_transfers' => $sentTransfers->map(fn ($t) => $this->transferRow($t)),
                'received_transfers' => $receivedTransfers->map(fn ($t) => $this->transferRow($t)),
            ],
        ]);
    }

    /** GET /api/v1/custodies/tracking — all custodies grouped by agent (supervisor read-only too) */
    public function tracking(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('approve_custody') || $user->can('view_all_records'), 403);

        $custodies = Custody::with(['agent', 'treasury'])->orderByDesc('created_at')->get();
        $accepted = $custodies->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed']);
        $active = $custodies->whereIn('status', ['accepted', 'active']);

        $agentsSummary = $active->filter(fn ($c) => $c->agent !== null)
            ->groupBy('agent_id')
            ->map(function ($rows) {
                $agent = $rows->first()->agent;

                return [
                    'agent' => ['id' => $agent->id, 'name' => $agent->name],
                    'count' => $rows->count(),
                    'total_amount' => number_format((float) $rows->sum('amount'), 2, '.', ''),
                    'transferred_in' => number_format((float) $rows->sum('transferred_in'), 2, '.', ''),
                    'transferred_out' => number_format((float) $rows->sum('transferred_out'), 2, '.', ''),
                    'spent' => number_format((float) $rows->sum('spent'), 2, '.', ''),
                    'returned' => number_format((float) $rows->sum('returned'), 2, '.', ''),
                    'remaining' => number_format((float) $rows->sum(fn ($c) => $c->getRemainingBalance()), 2, '.', ''),
                ];
            })->values();

        return response()->json([
            'data' => [
                'stats' => [
                    'total_custodies' => $custodies->count(),
                    'active' => $active->count(),
                    'pending' => $custodies->where('status', 'pending')->count(),
                    'rejected' => $custodies->where('status', 'rejected')->count(),
                    'closed' => $custodies->where('status', 'closed')->count(),
                    'total_amount' => number_format((float) $accepted->sum('amount'), 2, '.', ''),
                    'total_spent' => number_format((float) $accepted->sum('spent'), 2, '.', ''),
                    'total_returned' => number_format((float) $accepted->sum('returned'), 2, '.', ''),
                    'total_remaining' => number_format((float) $accepted->sum(fn ($c) => $c->getRemainingBalance()), 2, '.', ''),
                    'pending_returns' => number_format((float) $accepted->sum('pending_return'), 2, '.', ''),
                ],
                'agents_summary' => $agentsSummary,
                'custodies' => $custodies->map(fn ($c) => $this->row($c)),
            ],
        ]);
    }

    /** GET /api/v1/custodies/movements — the custody movements ledger (filters + summary) */
    public function movements(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('approve_custody') || $user->can('view_all_records'), 403);

        $base = function () use ($request) {
            $query = TreasuryTransaction::with([
                'custody.agent', 'custody.treasury', 'custodyTransfer.fromAgent',
                'custodyTransfer.toAgent', 'user', 'expense',
            ])
                ->where(fn ($q) => $q->whereNotNull('custody_id')->orWhereNotNull('custody_transfer_id'))
                ->where('type', '!=', 'custody_transfer_in');

            if (! $request->boolean('show_closures')) {
                $query->where(fn ($q) => $q->where('type', '!=', 'custody_close')->orWhere('amount', '>', 0));
            }
            if ($request->filled('date_from')) {
                $query->whereDate('transaction_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('transaction_date', '<=', $request->date_to);
            }
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            if ($request->filled('custody_id')) {
                $query->where('custody_id', $request->custody_id);
            }
            if ($request->filled('agent_id')) {
                $agentId = (int) $request->agent_id;
                $query->where(function ($q) use ($agentId) {
                    $q->whereHas('custody', fn ($c) => $c->where('agent_id', $agentId))
                        ->orWhereHas('custodyTransfer', fn ($t) => $t->where('from_agent_id', $agentId)->orWhere('to_agent_id', $agentId));
                });
            }

            return $query;
        };

        $summaryQuery = $base();
        $paginator = ApiList::paginate(
            $base()->orderByDesc('transaction_date')->orderByDesc('id'),
            $request,
            sortable: ['date' => 'transaction_date', 'amount' => 'amount', 'type' => 'type', 'description' => 'description', 'user' => 'user_id', 'custody_id' => 'custody_id'],
        );
        $paginator->getCollection()->transform(fn ($t) => $this->transactionRow($t));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'received' => number_format((float) (clone $summaryQuery)->where('type', 'custody_out')->sum('amount'), 2, '.', ''),
            'returned' => number_format((float) (clone $summaryQuery)->where('type', 'custody_return')->sum('amount'), 2, '.', ''),
            'transferred' => number_format((float) (clone $summaryQuery)->where('type', 'custody_transfer_out')->sum('amount'), 2, '.', ''),
            'spent' => number_format((float) (clone $summaryQuery)->where('type', 'expense')->sum('amount'), 2, '.', ''),
        ]));
    }

    /** GET /api/v1/custody-return-requests */
    public function returnRequests(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            CustodyReturnRequest::with(['custody.agent', 'requester', 'approver']),
            $request,
            sortable: ['date' => 'created_at', 'amount' => 'amount', 'custody_id' => 'custody_id', 'agent' => 'custody_id', 'requested_by' => 'requested_by', 'reason' => 'reason', 'status' => 'status'],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('reason', 'like', "%{$term}%")
                ->orWhereHas('custody.agent', fn ($a) => $a->where('name', 'like', "%{$term}%"))),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
        );

        $paginator->getCollection()->transform(fn ($r) => [
            'id' => $r->id,
            'custody_id' => $r->custody_id,
            'agent' => $r->custody?->agent?->name,
            'requested_by' => $r->requester?->name,
            'amount' => number_format((float) $r->amount, 2, '.', ''),
            'reason' => $r->reason,
            'status' => $r->status,
            'created_at' => $r->created_at?->toIso8601ZuluString(),
            'approved_by' => $r->approver?->name,
            'approved_at' => $r->approved_at?->toIso8601ZuluString(),
            'rejection_reason' => $r->status === 'rejected' ? $r->approval_notes : null,
        ]);

        return response()->json(ApiList::envelope($paginator));
    }

    /** GET /api/v1/agent/transactions — my treasury movements (agent + custody owners) */
    public function agentTransactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $custodies = Custody::where('agent_id', $user->id)->get();
        $active = $custodies->whereIn('status', ['active', 'accepted', 'partially_returned', 'closed']);

        $ids = $custodies->pluck('id');
        $paginator = ApiList::paginate(
            TreasuryTransaction::whereIn('custody_id', $ids),
            $request,
            sortable: ['date' => 'transaction_date', 'amount' => 'amount', 'type' => 'type', 'description' => 'description', 'user' => 'user_id', 'custody_id' => 'custody_id'],
            filters: ['type' => fn ($q, $v) => $q->where('type', $v)],
        );
        $paginator->getCollection()->transform(fn ($t) => $this->transactionRow($t));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'custodies_count' => $custodies->count(),
            'total_received' => number_format((float) ($active->sum('amount') + $active->sum('transferred_in')), 2, '.', ''),
            'total_spent' => number_format((float) $active->sum('spent'), 2, '.', ''),
            'total_returned' => number_format((float) $active->sum('returned'), 2, '.', ''),
            'transferred_in' => number_format((float) $active->sum('transferred_in'), 2, '.', ''),
            'transferred_out' => number_format((float) $active->sum('transferred_out'), 2, '.', ''),
            'remaining' => number_format((float) $active->sum(fn ($c) => $c->getRemainingBalance()), 2, '.', ''),
        ]));
    }

    /** GET /api/v1/user-custodies — custodies with balance for the expense form (T27) */
    public function userCustodies(Request $request): JsonResponse
    {
        $custodies = Custody::where('agent_id', $request->user()->id)
            ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
            ->get()
            ->map(function ($custody) {
                $remaining = (float) $custody->getRemainingBalance();

                return $remaining > 0 ? [
                    'id' => $custody->id,
                    'reason' => $custody->notes ?: 'عهدة #' . $custody->id,
                    'amount' => number_format((float) $custody->amount, 2, '.', ''),
                    'remaining' => number_format($remaining, 2, '.', ''),
                    // T27: إجمالي المتاح لو اختار المستخدم هذه العهدة (هي أولاً ثم بقية عهداته)
                    'total_available' => number_format($this->service->totalAvailableForCustody($custody->id), 2, '.', ''),
                ] : null;
            })->filter()->values();

        return response()->json(['data' => $custodies]);
    }

    // ── row mappers ──────────────────────────────────────────────

    private function row(Custody $c): array
    {
        return [
            'id' => $c->id,
            'agent' => $c->agent?->name,
            'treasury' => $c->treasury?->name,
            'amount' => number_format((float) $c->amount, 2, '.', ''),
            'transferred_in' => number_format((float) $c->transferred_in, 2, '.', ''),
            'transferred_out' => number_format((float) $c->transferred_out, 2, '.', ''),
            'spent' => number_format((float) $c->spent, 2, '.', ''),
            'returned' => number_format((float) $c->returned, 2, '.', ''),
            'pending_return' => number_format((float) $c->pending_return, 2, '.', ''),
            'frozen' => number_format((float) $c->pending_transfer_out, 2, '.', ''),
            'remaining' => number_format((float) $c->getRemainingBalance(), 2, '.', ''),
            'spent_percent' => ((float) $c->amount + (float) $c->transferred_in) > 0
                ? (int) round(((float) $c->spent / ((float) $c->amount + (float) $c->transferred_in)) * 100)
                : 0,
            'status' => $c->status,
            'status_detail' => $c->status_detail,
            'initiated_by' => $c->initiated_by,
            'notes' => $c->notes,
            'created_at' => $c->created_at?->toIso8601ZuluString(),
        ];
    }

    private function detail(Custody $c): array
    {
        return $this->row($c) + [
            'accountant' => $c->accountant?->name,
            'accepted_at' => $c->accepted_at?->toIso8601ZuluString(),
            'received_at' => $c->received_at?->toIso8601ZuluString(),
            'returned_at' => $c->returned_at?->toIso8601ZuluString(),
        ];
    }

    private function transferRow(\App\Models\CustodyTransfer $t): array
    {
        return [
            'id' => $t->id,
            'custody_id' => $t->custody_id,
            'sender' => $t->fromAgent?->name,
            'receiver' => $t->toAgent?->name,
            'amount' => number_format((float) $t->amount, 2, '.', ''),
            'status' => $t->status,
            'notes' => $t->notes,
            'created_at' => $t->created_at?->toIso8601ZuluString(),
        ];
    }

    private function transactionRow(TreasuryTransaction $t): array
    {
        return [
            'id' => $t->id,
            'type' => $t->type,
            'amount' => number_format((float) $t->amount, 2, '.', ''),
            'description' => $t->description,
            'user' => $t->user?->name,
            'date' => $t->transaction_date?->toIso8601ZuluString(),
            'custody_id' => $t->custody_id,
        ];
    }
}
