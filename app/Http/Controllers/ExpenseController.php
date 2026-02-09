<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Custody;
use App\Models\SocialCase;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Services\TreasuryService;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private TreasuryService $service) {}

    public function index()
    {
        $this->authorize('spend_money');
        return view('expenses.modern');
    }

    public function create()
    {
        $this->authorize('spend_money');
        $custodies = Custody::where('status', 'accepted')->get();
        $cases = SocialCase::where('status', 'approved')->get();
        $categories = ExpenseCategory::active()->with('items')->ordered()->get();

        // Check if current user is accountant (محاسب) - can spend from treasury
        $canSpendFromTreasury = auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير');

        return view('expenses.modern-create', compact('custodies', 'cases', 'categories', 'canSpendFromTreasury'));
    }

    public function store(Request $request)
    {
        $this->authorize('spend_money');

        // Determine source (default to custody)
        $source = $request->input('source', 'custody');

        if ($source === 'treasury') {
            // Direct treasury spending (for accountants)
            $this->authorize('direct_spend_from_treasury');

            $request->validate([
                'amount' => 'required|numeric|min:1',
                'expense_category_id' => 'required|exists:expense_categories,id',
                'expense_item_id' => 'required|exists:expense_items,id',
                'description' => 'required|string|max:500',
                'location' => 'nullable|string',
                'social_case_id' => 'nullable|exists:social_cases,id',
            ]);

            $this->service->recordDirectExpenseFromTreasury(
                auth()->id(),
                $request->amount,
                $request->expense_category_id,
                $request->expense_item_id,
                $request->description,
                $request->location,
                $request->social_case_id
            );
        } else {
            // Custody spending
            $request->validate([
                'custody_id' => 'required|exists:custodies,id',
                'amount' => 'required|numeric|min:1',
                'expense_category_id' => 'required|exists:expense_categories,id',
                'expense_item_id' => 'required|exists:expense_items,id',
                'description' => 'required|string|max:500',
                'location' => 'nullable|string',
                'social_case_id' => 'nullable|exists:social_cases,id',
            ]);

            $this->service->recordExpenseWithItems(
                $request->custody_id,
                auth()->id(),
                $request->amount,
                $request->expense_category_id,
                $request->expense_item_id,
                $request->description,
                $request->location,
                $request->social_case_id
            );
        }

        return redirect()->route('expenses.index')->with('success', 'تم تسجيل المصروف');
    }

    public function show(Expense $expense)
    {
        return view('expenses.modern-show', compact('expense'));
    }

    public function agentExpenses()
    {
        $user = auth()->user();

        // Check if user is agent (مندوب)
        if (!$user->hasRole('مندوب')) {
            abort(403, 'Unauthorized');
        }

        // Get agent's custodies
        $custodies = Custody::where('agent_id', $user->id)->pluck('id');

        // Get all expenses for agent's custodies
        $expenses = Expense::whereIn('custody_id', $custodies)->get();

        $totalExpenses = $expenses->sum('amount');
        $expenseCount = $expenses->count();
        $generalExpenseCount = $expenses->where('type', 'general')->count();
        $socialCaseExpenseCount = $expenses->where('type', 'social_case')->count();

        return view('expenses.agent-modern', compact('totalExpenses', 'expenseCount', 'generalExpenseCount', 'socialCaseExpenseCount'));
    }

    public function tableData()
    {
        $this->authorize('spend_money');
        $expenses = Expense::with(['user', 'custody', 'socialCase'])->get();

        return DataTables::of($expenses)
            ->addColumn('user_name', fn($row) => $row->user->name)
            ->addColumn('case_name', fn($row) => $row->socialCase->name ?? '-')
            ->addColumn('type_label', fn($row) => $row->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام')
            ->toJson();
    }

    public function agentExpensesData()
    {
        $user = auth()->user();

        // Check if user is agent (مندوب)
        if (!$user->hasRole('مندوب')) {
            abort(403, 'Unauthorized');
        }

        // Get agent's custodies
        $custodies = Custody::where('agent_id', $user->id)->pluck('id');

        // Get expenses for agent's custodies
        $expenses = Expense::with(['user', 'custody', 'socialCase'])
            ->whereIn('custody_id', $custodies)
            ->get();

        return DataTables::of($expenses)
            ->addColumn('case_name', fn($row) => $row->socialCase->name ?? '-')
            ->addColumn('type_label', fn($row) => $row->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام')
            ->toJson();
    }
}
