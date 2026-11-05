<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Support\Api\ApiList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        );

        return response()->json(ApiList::envelope($paginator, function () {
            // T36: بطاقتان فقط — عدد الخزائن وإجمالي الأرصدة (لا يوجد متوسط رصيد)
            return [
                'treasuries_count' => Treasury::count(),
                'total_balance' => number_format((float) Treasury::sum('balance'), 2, '.', ''),
            ];
        }));
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
