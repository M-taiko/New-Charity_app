<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Support\Api\ApiList;
use App\Support\Api\ListExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TreasuryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $paginator = ApiList::paginate(
            Treasury::query()->orderBy('name'),
            $request,
            sortable: ['name' => 'name', 'balance' => 'balance', 'created_at' => 'created_at', 'transactions_count' => 'raw:transactions_count'],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%")),
            // T7-07: declared allowlist — meta.filters drives the table UI
            filterable: [
                'balance' => ['type' => 'number-range', 'column' => 'balance'],
            ],
        );

        return response()->json(ApiList::envelope($paginator, function () {
            // T36: بطاقتان فقط — عدد الخزائن وإجمالي الأرصدة (لا يوجد متوسط رصيد)
            return [
                'treasuries_count' => Treasury::count(),
                'total_balance' => number_format((float) Treasury::sum('balance'), 2, '.', ''),
            ];
        }));
    }

    /**
     * GET /api/v1/treasury/export?format=xlsx|pdf|csv (T7-06) — the SAME
     * query/filters as index(), same authorization. withCount('transactions')
     * feeds the count column and the declared raw:transactions_count sort.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('manage_treasury');

        $rows = ListExport::rows(
            $request,
            Treasury::query()->withCount('transactions')->orderBy('name'),
            ['name' => 'name', 'balance' => 'balance', 'created_at' => 'created_at', 'transactions_count' => 'raw:transactions_count'],
            [
                'balance' => ['type' => 'number-range', 'column' => 'balance'],
            ],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%")),
            row: fn (Treasury $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'balance' => (float) $t->balance,
                'transactions_count' => $t->transactions_count,
                'notes' => $t->notes,
                'created_at' => optional($t->created_at)->format('Y-m-d'),
            ],
        );

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'الخزينة'],
            ['key' => 'balance', 'label' => 'الرصيد', 'numeric' => true, 'total' => true],
            ['key' => 'transactions_count', 'label' => 'عدد الحركات', 'numeric' => true],
            ['key' => 'notes', 'label' => 'ملاحظات'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'treasury');
    }

    public function show(Request $request, Treasury $treasury): JsonResponse
    {
        $this->authorize('manage_treasury');

        $transactions = ApiList::paginate(
            TreasuryTransaction::where('treasury_id', $treasury->id)->with('user'),
            $request,
            sortable: ['date' => 'transaction_date', 'amount' => 'amount', 'type' => 'type', 'description' => 'description', 'user' => 'user_id'],
            filterable: [
                'type' => ['type' => 'select', 'column' => 'type', 'options' => fn () => TreasuryTransaction::distinct()->pluck('type')->map(fn ($t) => ['value' => $t, 'label' => $t])->values()->all()],
                'date' => ['type' => 'date-range', 'column' => 'transaction_date'],
            ],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('description', 'like', "%{$term}%")
                ->orWhere('type', 'like', "%{$term}%")),
            filters: ['type' => fn ($q, $v) => $q->where('type', $v)],
        );

        // Same real cash-flow statistics as the Blade detail page
        $totalIn = $treasury->transactions()
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('type', 'donation')->whereNull('custody_id');
                })->orWhereIn('type', ['custody_return', 'transfer_in']);
            })->sum('amount');
        $totalOut = $treasury->transactions()
            ->where(function ($q) {
                $q->where('type', 'custody_out')
                    ->orWhere(function ($sq) {
                        $sq->where('type', 'expense')->whereNull('custody_id');
                    })
                    ->orWhere('type', 'transfer_out');
            })->sum('amount');

        return response()->json([
            'data' => [
                'id' => $treasury->id,
                'name' => $treasury->name,
                'balance' => number_format((float) $treasury->balance, 2, '.', ''),
                'notes' => $treasury->notes,
                'created_at' => $treasury->created_at?->toIso8601ZuluString(),
                'stats' => [
                    'total_in' => number_format((float) $totalIn, 2, '.', ''),
                    'total_out' => number_format((float) $totalOut, 2, '.', ''),
                    'total_transactions' => (int) $treasury->transactions()->count(),
                ],
                'transactions' => ApiList::envelope($transactions),
            ],
        ]);
    }
}
