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

        // Treasury data
        $treasury = Treasury::first();
        $treasuryBalance = $treasury?->balance ?? 0;

        // Custody statistics
        $totalCustodies = Custody::count();
        $activeCustodies = Custody::whereIn('status', ['accepted', 'active'])->count();
        $closedCustodies = Custody::where('status', 'closed')->count();
        $rejectedCustodies = Custody::where('status', 'rejected')->count();

        $custodyAmount = Custody::sum('amount');
        $custodySpent = Custody::sum('spent');
        $custodyReturned = Custody::sum('returned');

        // Expense statistics
        $totalExpenses = Expense::sum('amount');
        $expensesToday = Expense::whereDate('created_at', now()->today())->sum('amount');
        $expensesThisMonth = Expense::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->sum('amount');
        $expensesThisYear = Expense::whereYear('created_at', now()->year)->sum('amount');

        $socialCaseExpenses = Expense::where('type', 'social_case')->sum('amount');
        $custodyExpenses = Expense::where('source', 'custody')->sum('amount');
        $treasuryExpenses = Expense::where('source', 'treasury')->sum('amount');

        // Social cases statistics
        $totalSocialCases = SocialCase::count();
        $approvedCases = SocialCase::where('status', 'approved')->count();
        $pendingCases = SocialCase::where('status', 'pending')->count();
        $rejectedCases = SocialCase::where('status', 'rejected')->count();
        $socialCaseSpent = Expense::where('type', 'social_case')->sum('amount');

        // Expenses by main category (root level only)
        try {
            $expensesByCategory = ExpenseCategory::roots()
                ->with('children.children.items')
                ->get()
                ->map(function($root) {
                    // Get all items under this root (including nested items)
                    $allItemIds = collect();
                    $allItemIds = $allItemIds->merge($root->items->pluck('id'));

                    foreach ($root->children as $level2) {
                        $allItemIds = $allItemIds->merge($level2->items->pluck('id'));
                        foreach ($level2->children as $level3) {
                            $allItemIds = $allItemIds->merge($level3->items->pluck('id'));
                        }
                    }

                    // Sum expenses from items OR from category directly
                    $itemAmount = Expense::whereIn('expense_item_id', $allItemIds->toArray())->sum('amount');
                    $categoryAmount = Expense::where('expense_category_id', $root->id)->sum('amount');
                    $totalAmount = $itemAmount + $categoryAmount;

                    return [
                        'id' => $root->id,
                        'name' => $root->name,
                        'amount' => $totalAmount,
                        'count' => Expense::whereIn('expense_item_id', $allItemIds->toArray())
                            ->orWhere('expense_category_id', $root->id)
                            ->count(),
                    ];
                })
                ->filter(fn($cat) => $cat['amount'] > 0)
                ->sortByDesc('amount')
                ->values();
        } catch (\Exception $e) {
            $expensesByCategory = collect([]);
        }

        // Expenses by date (last 7 days)
        try {
            $expensesByDate = collect();
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $amount = Expense::whereDate('created_at', $date)->sum('amount');
                $expensesByDate->push([
                    'date' => now()->subDays($i)->format('d-m'),
                    'fullDate' => now()->subDays($i)->format('Y-m-d'),
                    'amount' => $amount,
                ]);
            }
        } catch (\Exception $e) {
            $expensesByDate = collect([]);
        }

        // Custodies by agents (top 10) - showing remaining balance after spending
        try {
            $custodiesByAgents = Custody::with('agent')
                ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
                ->get()
                ->groupBy('agent_id')
                ->map(function($custodies) {
                    $agent = $custodies->first()?->agent;
                    $remaining = $custodies->sum(fn($c) => $c->getRemainingBalance());
                    return [
                        'agent_id' => $agent?->id,
                        'agent_name' => $agent?->name ?? 'غير معروف',
                        'amount' => $custodies->sum('amount'),
                        'spent' => $custodies->sum('spent'),
                        'remaining' => $remaining,
                        'count' => $custodies->count(),
                    ];
                })
                ->sortByDesc('remaining')  // Sort by remaining balance (after spending)
                ->take(10)
                ->values();
        } catch (\Exception $e) {
            $custodiesByAgents = collect([]);
        }

        // Social cases by researchers (top 10)
        try {
            $casesByResearchers = SocialCase::with('researcher')
                ->where('status', 'approved')
                ->get()
                ->groupBy('researcher_id')
                ->map(function($cases) {
                    $researcher = $cases->first()?->researcher;
                    $spent = Expense::where('type', 'social_case')
                        ->whereIn('social_case_id', $cases->pluck('id')->toArray())
                        ->sum('amount');
                    return [
                        'researcher_id' => $researcher?->id,
                        'researcher_name' => $researcher?->name ?? 'غير معروف',
                        'cases_count' => $cases->count(),
                        'spent' => $spent,
                        'avg_spent' => $cases->count() > 0 ? $spent / $cases->count() : 0,
                    ];
                })
                ->sortByDesc('spent')
                ->take(10)
                ->values();
        } catch (\Exception $e) {
            $casesByResearchers = collect([]);
        }

        // Agents statistics
        try {
            $agentsWithCustodies = User::whereHas('custodies')->count();
        } catch (\Exception $e) {
            $agentsWithCustodies = 0;
        }
        $averageCustodyAmount = $totalCustodies > 0 ? $custodyAmount / $totalCustodies : 0;

        return view('reports.dashboard', compact(
            'treasuryBalance',
            'totalCustodies',
            'activeCustodies',
            'closedCustodies',
            'rejectedCustodies',
            'custodyAmount',
            'custodySpent',
            'custodyReturned',
            'totalExpenses',
            'expensesToday',
            'expensesThisMonth',
            'expensesThisYear',
            'socialCaseExpenses',
            'custodyExpenses',
            'treasuryExpenses',
            'totalSocialCases',
            'approvedCases',
            'pendingCases',
            'rejectedCases',
            'socialCaseSpent',
            'expensesByCategory',
            'expensesByDate',
            'agentsWithCustodies',
            'averageCustodyAmount',
            'custodiesByAgents',
            'casesByResearchers'
        ));
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

    public function expenseCategoriesAnalytics(Request $request): View
    {
        $this->authorize('manage_treasury');

        // Get filter parameters
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Helper function to calculate expenses for a category and get all child items
        $getExpensesForCategory = function($categoryId) use ($dateFrom, $dateTo) {
            $query = Expense::query();

            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            return $query->where(function($q) use ($categoryId) {
                $q->where('expense_category_id', $categoryId);
            })->get();
        };

        // Get all roots with their hierarchy
        $roots = ExpenseCategory::roots()->active()->with('children.children.items', 'items')->ordered()->get();

        // Process all data hierarchically
        $allData = collect(); // All items for charts
        $categoriesData = collect(); // Root categories only

        foreach ($roots as $root) {
            $rootExpenses = $getExpensesForCategory($root->id);
            $rootTotal = $rootExpenses->sum('amount');

            $rootData = [
                'id' => $root->id,
                'name' => $root->name,
                'level' => 1,
                'code' => $root->code,
                'total_amount' => $rootTotal,
                'expense_count' => $rootExpenses->count(),
                'average_amount' => $rootExpenses->count() > 0 ? $rootExpenses->avg('amount') : 0,
            ];
            $allData->push($rootData);
            $categoriesData->push($rootData);

            // Process Level 2
            foreach ($root->children as $level2) {
                $level2Expenses = $getExpensesForCategory($level2->id);
                $level2Total = $level2Expenses->sum('amount');

                $level2Data = [
                    'id' => $level2->id,
                    'name' => $level2->name,
                    'level' => 2,
                    'code' => $level2->code,
                    'parent_name' => $root->name,
                    'total_amount' => $level2Total,
                    'expense_count' => $level2Expenses->count(),
                    'average_amount' => $level2Expenses->count() > 0 ? $level2Expenses->avg('amount') : 0,
                ];
                $allData->push($level2Data);

                // Process Level 3
                foreach ($level2->children as $level3) {
                    $level3Expenses = $getExpensesForCategory($level3->id);
                    $level3Total = $level3Expenses->sum('amount');

                    $level3Data = [
                        'id' => $level3->id,
                        'name' => $level3->name,
                        'level' => 3,
                        'code' => $level3->code,
                        'parent_name' => $level2->name,
                        'total_amount' => $level3Total,
                        'expense_count' => $level3Expenses->count(),
                        'average_amount' => $level3Expenses->count() > 0 ? $level3Expenses->avg('amount') : 0,
                    ];
                    $allData->push($level3Data);
                }

                // Process Items under level 2
                foreach ($level2->items as $item) {
                    $itemExpenses = Expense::where('expense_item_id', $item->id)
                        ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
                        ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo))
                        ->get();

                    $itemTotal = $itemExpenses->sum('amount');
                    if ($itemTotal > 0) {
                        $allData->push([
                            'id' => $item->id,
                            'name' => $item->name,
                            'level' => 'item',
                            'code' => $item->code,
                            'parent_name' => $level2->name,
                            'total_amount' => $itemTotal,
                            'expense_count' => $itemExpenses->count(),
                            'average_amount' => $itemExpenses->count() > 0 ? $itemExpenses->avg('amount') : 0,
                        ]);
                    }
                }
            }

            // Process items directly under root
            foreach ($root->items as $item) {
                $itemExpenses = Expense::where('expense_item_id', $item->id)
                    ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo))
                    ->get();

                $itemTotal = $itemExpenses->sum('amount');
                if ($itemTotal > 0) {
                    $allData->push([
                        'id' => $item->id,
                        'name' => $item->name,
                        'level' => 'item',
                        'code' => $item->code,
                        'parent_name' => $root->name,
                        'total_amount' => $itemTotal,
                        'expense_count' => $itemExpenses->count(),
                        'average_amount' => $itemExpenses->count() > 0 ? $itemExpenses->avg('amount') : 0,
                    ]);
                }
            }
        }

        // Filter out zero amounts
        $allData = $allData->filter(fn($d) => $d['total_amount'] > 0)->values();

        // Calculate grand total and percentages
        $grandTotal = $allData->sum('total_amount');
        $allData = $allData->map(function($data) use ($grandTotal) {
            $data['percentage'] = $grandTotal > 0 ? round(($data['total_amount'] / $grandTotal) * 100, 2) : 0;
            return $data;
        })->values();

        return view('reports.expense-categories-analytics', compact(
            'allData',
            'categoriesData',
            'dateFrom',
            'dateTo',
            'grandTotal'
        ));
    }
}
