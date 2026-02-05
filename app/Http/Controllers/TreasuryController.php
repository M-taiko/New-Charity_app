<?php

namespace App\Http\Controllers;

use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    public function __construct(private TreasuryService $service) {}

    public function index()
    {
        $this->authorize('manage_treasury');
        $treasury = Treasury::first();

        if (!$treasury) {
            return view('treasury.modern', ['treasury' => null, 'error' => 'لم يتم العثور على خزينة. يرجى الاتصال بالمسؤول.']);
        }

        return view('treasury.modern', compact('treasury'));
    }

    public function store(Request $request)
    {
        $this->authorize('manage_treasury');
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'source' => 'required|in:company,external',
        ]);

        $this->service->addDonation(
            $request->amount,
            $request->source,
            $request->description,
            auth()->id()
        );

        return back()->with('success', 'تم إضافة التبرع بنجاح');
    }

    public function transactionsData()
    {
        $this->authorize('manage_treasury');
        $transactions = TreasuryTransaction::with(['user', 'custody'])
            ->latest()
            ->get();

        return DataTables::of($transactions)
            ->addColumn('type_label', fn($row) => $this->getTransactionTypeLabel($row->type))
            ->addColumn('source_label', fn($row) => $row->source ? (__('messages.' . $row->source)) : '-')
            ->rawColumns(['type_label'])
            ->toJson();
    }

    private function getTransactionTypeLabel($type)
    {
        $labels = [
            'donation' => '<span class="badge bg-success">تبرع</span>',
            'expense' => '<span class="badge bg-danger">مصروف</span>',
            'custody_out' => '<span class="badge bg-info">عهدة صرف</span>',
            'custody_return' => '<span class="badge bg-primary">عهدة إرجاع</span>',
        ];
        return $labels[$type] ?? '';
    }
}
