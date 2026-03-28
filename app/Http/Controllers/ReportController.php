<?php

namespace App\Http\Controllers;

use App\Models\Treasury;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\SocialCase;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\User;
use Illuminate\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function dashboard(): View
    {
        $this->authorize('manage_treasury');

        return view('reports.dashboard');
    }

    public function researcherStats(): View
    {
        $this->authorize('manage_treasury');

        return view('analytics.researcher-stats');
    }

    public function socialCaseExpensesReport(Request $request): View
    {
        $this->authorize('manage_treasury');

        // Get filter parameters
        $socialCaseId = $request->input('social_case_id');
        $categoryId = $request->input('category_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Build query
        $query = Expense::with(['user', 'socialCase', 'category', 'item']);

        if ($socialCaseId) {
            $query->where('social_case_id', $socialCaseId);
        }

        if ($categoryId) {
            $query->where('expense_category_id', $categoryId);
        }

        if ($dateFrom) {
            $query->whereDate('expense_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('expense_date', '<=', $dateTo);
        }

        $expenses = $query->get();

        // Calculate statistics
        $totalAmount = $expenses->sum('amount');
        $expenseCount = $expenses->count();

        // Group by category
        $expensesByCategory = $expenses->groupBy('category.name')->map(function ($items) {
            return [
                'amount' => $items->sum('amount'),
                'count' => $items->count(),
                'percentage' => 0, // Will be calculated below
            ];
        });

        if ($totalAmount > 0) {
            foreach ($expensesByCategory as &$category) {
                $category['percentage'] = round(($category['amount'] / $totalAmount) * 100, 2);
            }
        }

        // Get social cases and categories for filters
        $socialCases = SocialCase::where('status', 'approved')->get();
        $categories = ExpenseCategory::active()->get();

        return view('reports.social-case-expenses', compact(
            'expenses',
            'totalAmount',
            'expenseCount',
            'expensesByCategory',
            'socialCases',
            'categories',
            'socialCaseId',
            'categoryId',
            'dateFrom',
            'dateTo'
        ));
    }

    public function expenseItemsReport(Request $request): View
    {
        $this->authorize('manage_treasury');

        // Get filter parameters
        $categoryId = $request->input('category_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Get all items with their expenses
        $query = ExpenseItem::with('expenses')->active();

        if ($categoryId) {
            $query->where('expense_category_id', $categoryId);
        }

        $items = $query->ordered()->get();

        // Calculate totals for each item
        $itemsData = $items->map(function ($item) use ($dateFrom, $dateTo) {
            $expensesQuery = $item->expenses();

            if ($dateFrom) {
                $expensesQuery->whereDate('expense_date', '>=', $dateFrom);
            }

            if ($dateTo) {
                $expensesQuery->whereDate('expense_date', '<=', $dateTo);
            }

            $expenses = $expensesQuery->get();

            return [
                'item' => $item,
                'total_amount' => $expenses->sum('amount'),
                'total_count' => $expenses->count(),
                'average_amount' => $expenses->count() > 0 ? $expenses->avg('amount') : 0,
            ];
        })->filter(function ($data) {
            return $data['total_count'] > 0; // Only show items with expenses
        });

        // Get categories for filter
        $categories = ExpenseCategory::active()->get();

        // Calculate grand totals
        $totalAmount = $itemsData->sum('total_amount');
        $totalExpenses = $itemsData->sum('total_count');

        return view('reports.expense-items', compact(
            'itemsData',
            'categories',
            'categoryId',
            'dateFrom',
            'dateTo',
            'totalAmount',
            'totalExpenses'
        ));
    }

    public function agentsBalanceReport(): View
    {
        // Only managers and accountants can access this report
        $this->authorize('manage_treasury');

        // Get all users with 'مندوب' role
        $agents = User::whereHas('roles', function ($query) {
            $query->where('name', 'مندوب');
        })->get();

        // Calculate balance for each agent
        $agentsData = $agents->map(function ($agent) {
            // Get only accepted, active, partially returned, and closed custodies
            // Exclude pending and rejected custodies as they don't represent actual money flow
            $custodies = Custody::where('agent_id', $agent->id)
                ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
                ->get();

            // Calculate totals
            $totalCustodies = $custodies->count();
            $activeCustodies = $custodies->whereIn('status', ['accepted', 'active', 'partially_returned'])->count();
            $closedCustodies = $custodies->where('status', 'closed')->count();

            // Calculate financial totals
            $totalReceived = $custodies->sum('amount');
            $totalSpent = $custodies->sum('spent');
            $totalReturned = $custodies->sum('returned');
            $currentBalance = $custodies->sum(function ($custody) {
                return $custody->getRemainingBalance();
            });

            return [
                'agent' => $agent,
                'total_custodies' => $totalCustodies,
                'active_custodies' => $activeCustodies,
                'closed_custodies' => $closedCustodies,
                'total_received' => $totalReceived,
                'total_spent' => $totalSpent,
                'total_returned' => $totalReturned,
                'current_balance' => $currentBalance,
            ];
        })->filter(function ($data) {
            // Only show agents who have received custodies
            return $data['total_custodies'] > 0;
        })->sortByDesc('current_balance');

        // Calculate grand totals
        $grandTotals = [
            'total_agents' => $agentsData->count(),
            'total_received' => $agentsData->sum('total_received'),
            'total_spent' => $agentsData->sum('total_spent'),
            'total_returned' => $agentsData->sum('total_returned'),
            'total_balance' => $agentsData->sum('current_balance'),
        ];

        return view('reports.agents-balance', compact('agentsData', 'grandTotals'));
    }

    /**
     * Reconciliation Report - Verify that treasury balance matches accounting equations
     */
    public function reconciliation(): View
    {
        $this->authorize('manage_treasury');

        $treasury = Treasury::first();

        if (!$treasury) {
            abort(404, 'لم يتم العثور على خزينة');
        }

        // 1. Current treasury balance
        $treasuryCurrentBalance = $treasury->balance;

        // 2. Total donations received
        $totalDonations = \App\Models\TreasuryTransaction::where('type', 'donation')
            ->sum('amount');

        // 3. Total custodies issued (amount given to agents)
        $totalCustodiesIssued = Custody::whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
            ->sum('amount');

        // 4. Total custodies returned
        $totalCustodiesReturned = Custody::whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
            ->sum('returned');

        // 5. Active custody balances (still with agents)
        $activeCustodyBalance = Custody::whereIn('status', ['accepted', 'active', 'partially_returned'])
            ->get()
            ->sum(function ($custody) {
                return $custody->getRemainingBalance();
            });

        // 6. Total direct expenses from treasury
        $totalDirectExpenses = Expense::where('source', 'treasury')
            ->sum('amount');

        // 7. Total expenses from custodies
        $totalCustodyExpenses = Expense::where('source', 'custody')
            ->sum('amount');

        // 8. Calculate expected treasury balance
        // Formula: Balance = Donations - (Custodies Issued - Custodies Returned) - Direct Expenses
        $expectedBalance = $totalDonations - ($totalCustodiesIssued - $totalCustodiesReturned) - $totalDirectExpenses;

        // 9. Calculate difference
        $difference = $treasuryCurrentBalance - $expectedBalance;

        // 10. Check reconciliation
        $isReconciled = abs($difference) < 0.01; // Allow for 0.01 due to rounding

        // Detailed breakdown
        $reconciliation = [
            'actual_balance' => $treasuryCurrentBalance,
            'expected_balance' => $expectedBalance,
            'difference' => $difference,
            'is_reconciled' => $isReconciled,
            'total_donations' => $totalDonations,
            'total_custodies_issued' => $totalCustodiesIssued,
            'total_custodies_returned' => $totalCustodiesReturned,
            'active_custody_balance' => $activeCustodyBalance,
            'total_direct_expenses' => $totalDirectExpenses,
            'total_custody_expenses' => $totalCustodyExpenses,
            'total_all_expenses' => $totalDirectExpenses + $totalCustodyExpenses,
        ];

        return view('reports.reconciliation', compact('reconciliation'));
    }
}
