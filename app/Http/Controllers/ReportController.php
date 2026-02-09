<?php

namespace App\Http\Controllers;

use App\Models\Treasury;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\SocialCase;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
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
}
