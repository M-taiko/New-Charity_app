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

    /**
     * Display list of all treasuries
     */
    public function index()
    {
        $this->authorize('manage_treasury');
        $treasuries = Treasury::all();

        return view('treasury.index', compact('treasuries'));
    }

    /**
     * Show treasury creation form
     */
    public function create()
    {
        $this->authorize('manage_treasury');
        return view('treasury.create');
    }

    /**
     * Store a new treasury
     */
    public function store(Request $request)
    {
        $this->authorize('manage_treasury');
        $request->validate([
            'name' => 'required|string|max:255|unique:treasuries',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            Treasury::create($request->only(['name', 'notes']));
            return redirect()->route('treasury.index')->with('success', 'تم إنشاء الخزينة بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Show treasury details and transactions
     */
    public function show(Treasury $treasury)
    {
        $this->authorize('manage_treasury');
        $transactions = $treasury->transactions()->latest()->paginate(20);

        // Calculate statistics
        $stats = [
            'total_in' => $treasury->transactions()
                ->whereIn('type', ['donation', 'transfer_in'])
                ->sum('amount'),
            'total_out' => $treasury->transactions()
                ->whereIn('type', ['expense', 'transfer_out', 'custody_out'])
                ->sum('amount'),
            'total_transactions' => $treasury->transactions()->count(),
        ];

        return view('treasury.show', compact('treasury', 'transactions', 'stats'));
    }

    /**
     * Show treasury edit form
     */
    public function edit(Treasury $treasury)
    {
        $this->authorize('manage_treasury');
        return view('treasury.edit', compact('treasury'));
    }

    /**
     * Update treasury
     */
    public function update(Request $request, Treasury $treasury)
    {
        $this->authorize('manage_treasury');
        $request->validate([
            'name' => 'required|string|max:255|unique:treasuries,name,' . $treasury->id,
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $treasury->update($request->only(['name', 'notes']));
            return redirect()->route('treasury.show', $treasury)->with('success', 'تم تحديث الخزينة بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Delete treasury
     */
    public function destroy(Treasury $treasury)
    {
        $this->authorize('manage_treasury');

        // Check if treasury has transactions or balance
        if ($treasury->transactions()->count() > 0 || $treasury->balance > 0) {
            return back()->with('error', 'لا يمكن حذف خزينة بها حركات أو رصيد');
        }

        try {
            $treasury->delete();
            return redirect()->route('treasury.index')->with('success', 'تم حذف الخزينة بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Add donation to treasury (changed from store method)
     */
    public function addDonation(Request $request, Treasury $treasury)
    {
        $this->authorize('manage_treasury');
        $rules = [
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string',
            'source' => 'required|in:company,external,returnings',
        ];

        // External source name is required if source is external
        if ($request->source === 'external') {
            $rules['external_source'] = 'required|string|max:255';
        }

        $request->validate($rules);

        try {
            // Build the source string for the transaction
            $sourceString = $request->source;
            if ($request->source === 'external' && $request->filled('external_source')) {
                $sourceString = 'external: ' . $request->external_source;
            }

            $this->service->addDonation(
                $treasury->id,
                $request->amount,
                $sourceString,
                $request->description,
                auth()->id()
            );

            return back()->with('success', 'تم إضافة التبرع بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ أثناء إضافة التبرع: ' . $e->getMessage());
        }
    }

    /**
     * Show transfer form
     */
    public function transfer()
    {
        $this->authorize('manage_treasury');
        $treasuries = Treasury::where('balance', '>', 0)->get();

        if ($treasuries->count() < 2) {
            return back()->with('warning', 'يجب أن تكون هناك خزينتان على الأقل لتنفيذ تحويل');
        }

        return view('treasury.transfer', compact('treasuries'));
    }

    /**
     * Perform transfer between treasuries
     */
    public function performTransfer(Request $request)
    {
        $this->authorize('manage_treasury');
        $request->validate([
            'from_treasury_id' => 'required|exists:treasuries,id',
            'to_treasury_id' => 'required|exists:treasuries,id|different:from_treasury_id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
        ]);

        try {
            $this->service->transferBetweenTreasuries(
                $request->from_treasury_id,
                $request->to_treasury_id,
                $request->amount,
                $request->description,
                auth()->id()
            );

            return redirect()->route('treasury.show', $request->from_treasury_id)
                ->with('success', 'تم تحويل الأموال بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Get treasury transactions (API endpoint for DataTables)
     */
    public function transactionsData(Treasury $treasury)
    {
        $this->authorize('manage_treasury');
        $transactions = $treasury->transactions()->with(['user', 'custody'])->latest()->get();

        return DataTables::of($transactions)
            ->addColumn('type_label', fn($row) => $this->getTransactionTypeLabel($row->type))
            ->addColumn('source_label', fn($row) => $row->source ? $row->source : '-')
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
            'transfer_in' => '<span class="badge bg-primary">تحويل وارد</span>',
            'transfer_out' => '<span class="badge bg-warning">تحويل صادر</span>',
        ];
        return $labels[$type] ?? '<span class="badge bg-secondary">غير محدد</span>';
    }
}
