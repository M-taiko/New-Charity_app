<?php

namespace App\Http\Controllers;

use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CustodyMovementController extends Controller
{
    /**
     * صفحة حركات العهد (استلام / تسليم / تحويل / صرف) - للقراءة فقط
     */
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->can('approve_custody') || $user->can('view_all_records'), 403);

        $agents = User::role('مندوب')->orderBy('name')->get();

        return view('custodies.movements', compact('agents'));
    }

    public function data(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->can('approve_custody') || $user->can('view_all_records'), 403);

        $query = $this->baseQuery($request);

        // ملخص متأثر بالفلاتر
        $summaryQuery = $this->baseQuery($request);
        $summary = [
            'received'  => (clone $summaryQuery)->where('type', 'custody_out')->sum('amount'),
            'returned'  => (clone $summaryQuery)->where('type', 'custody_return')->sum('amount'),
            'transferred' => (clone $summaryQuery)->where('type', 'custody_transfer_out')->sum('amount'),
            'spent'     => (clone $summaryQuery)->where('type', 'expense')->sum('amount'),
        ];

        return DataTables::of($query->orderByDesc('transaction_date')->orderByDesc('id'))
            ->editColumn('transaction_date', fn($row) => $row->transaction_date?->toIso8601String())
            ->addColumn('movement_label', fn($row) => $this->movementLabel($row->type))
            ->addColumn('from_party', fn($row) => $this->fromParty($row))
            ->addColumn('to_party', fn($row) => $this->toParty($row))
            ->addColumn('custody_link', fn($row) => $row->custody_id
                ? '<a href="' . route('custodies.show', $row->custody_id) . '">#' . $row->custody_id . '</a>'
                : '-')
            ->addColumn('expense_link', fn($row) => ($row->type === 'expense' && $row->expense_id)
                ? '<a href="' . route('expenses.show', $row->expense_id) . '">#' . $row->expense_id . '</a>'
                : '-')
            ->addColumn('performed_by', fn($row) => $row->user?->name ?? '-')
            ->rawColumns(['custody_link', 'expense_link'])
            ->with('summary', $summary)
            ->toJson();
    }

    private function baseQuery(Request $request)
    {
        $query = TreasuryTransaction::with([
            'custody.agent',
            'custody.treasury',
            'custodyTransfer.fromAgent',
            'custodyTransfer.toAgent',
            'user',
            'expense',
        ])
            ->where(function ($q) {
                $q->whereNotNull('custody_id')->orWhereNotNull('custody_transfer_id');
            })
            // سطر واحد لكل تحويل: نعرض custody_transfer_out فقط لتجنب التكرار
            ->where('type', '!=', 'custody_transfer_in');

        // إغلاقات العهد بمبلغ صفري: مخفية افتراضياً
        if (!$request->boolean('show_closures')) {
            $query->where(function ($q) {
                $q->where('type', '!=', 'custody_close')->orWhere('amount', '>', 0);
            });
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
            $agentId = $request->agent_id;
            $query->where(function ($q) use ($agentId) {
                $q->whereHas('custody', fn($c) => $c->where('agent_id', $agentId))
                    ->orWhereHas('custodyTransfer', fn($t) => $t->where('from_agent_id', $agentId)->orWhere('to_agent_id', $agentId));
            });
        }

        return $query;
    }

    private function movementLabel(string $type): string
    {
        return match ($type) {
            'custody_out' => 'صرف عهدة',
            'custody_return' => 'رد عهدة',
            'custody_transfer_out' => 'تحويل بين مندوبين',
            'expense' => 'مصروف',
            'recovery' => 'تحصيل',
            'custody_close' => 'إغلاق عهدة',
            default => $type,
        };
    }

    private function fromParty(TreasuryTransaction $row): string
    {
        return match ($row->type) {
            'custody_out' => $row->custody?->treasury?->name ?? 'الخزينة',
            'custody_return', 'expense', 'custody_close', 'recovery' => $row->custody?->agent?->name ?? '-',
            'custody_transfer_out' => $row->custodyTransfer?->fromAgent?->name ?? '-',
            default => '-',
        };
    }

    private function toParty(TreasuryTransaction $row): string
    {
        return match ($row->type) {
            'custody_out' => $row->custody?->agent?->name ?? '-',
            'custody_return', 'recovery' => $row->custody?->treasury?->name ?? 'الخزينة',
            'expense' => 'إنفاق',
            'custody_close' => 'إغلاق',
            'custody_transfer_out' => $row->custodyTransfer?->toAgent?->name ?? '-',
            default => '-',
        };
    }
}
