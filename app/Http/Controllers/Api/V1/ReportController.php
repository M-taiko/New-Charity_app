<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseItem;
use App\Models\SocialCase;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\DirectionalStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class ReportController extends Controller
{
    public function __construct(private DirectionalStatsService $directionStats) {}

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function directionParams(Request $request): array
    {
        $directionId = (int) $request->input('direction') ?: null;
        if ($directionId && ! ExpenseCategory::whereKey($directionId)->exists()) {
            $directionId = null;
        }

        return [$directionId, (int) $request->input('item') ?: null];
    }

    /** GET /api/v1/reports — the reports dashboard (T34 governorate card included) */
    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $figures = $this->directionStats->figures(null, null);

        // فئات المستوى الأول بمجاميعها (عبر المساعد المشترك) لرسم المصروفات حسب الفئة
        $expensesByCategory = array_map(fn (array $child) => [
            'id' => $child['id'],
            'name' => $child['name'],
            'amount' => $this->money($child['amount']),
        ], $this->directionStats->explorer(null, null)['children']);

        $treasury = Treasury::first();

        $custodiesByAgents = Custody::with('agent')
            ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
            ->get()
            ->groupBy('agent_id')
            ->map(function ($rows) {
                return [
                    'agent_id' => $rows->first()?->agent?->id,
                    'agent_name' => $rows->first()?->agent?->name ?? 'غير معروف',
                    'amount' => $this->money($rows->sum('amount')),
                    'spent' => $this->money($rows->sum('spent')),
                    'remaining' => $this->money($rows->sum(fn ($c) => $c->getRemainingBalance())),
                    'count' => $rows->count(),
                ];
            })->sortByDesc('remaining')->take(10)->values();

        $casesByResearchers = SocialCase::with('researcher')->where('status', 'approved')->get()
            ->groupBy('researcher_id')
            ->map(function ($cases) {
                $spent = Expense::where('type', 'social_case')
                    ->whereIn('social_case_id', $cases->pluck('id'))->sum('amount');

                return [
                    'researcher_id' => $cases->first()?->researcher?->id,
                    'researcher_name' => $cases->first()?->researcher?->name ?? 'غير معروف',
                    'cases_count' => $cases->count(),
                    'spent' => $this->money($spent),
                    'avg_spent' => $this->money($cases->count() > 0 ? $spent / $cases->count() : 0),
                ];
            })->sortByDesc('spent')->take(10)->values();

        return response()->json([
            'data' => [
                // fixed figures (do not react to the direction filter)
                'treasury_balance' => $this->money($treasury?->balance ?? 0),
                'total_custodies' => Custody::count(),
                'active_custodies' => Custody::whereIn('status', ['accepted', 'active'])->count(),
                'closed_custodies' => Custody::where('status', 'closed')->count(),
                'rejected_custodies' => Custody::where('status', 'rejected')->count(),
                'custody_amount' => $this->money(Custody::sum('amount')),
                'custody_spent' => $this->money(Custody::sum('spent')),
                'custody_returned' => $this->money(Custody::sum('returned')),
                'total_social_cases' => SocialCase::count(),
                'approved_cases' => SocialCase::where('status', 'approved')->count(),
                'pending_cases' => SocialCase::where('status', 'pending')->count(),
                'rejected_cases' => SocialCase::where('status', 'rejected')->count(),
                'social_case_expenses' => $this->money(Expense::where('type', 'social_case')->sum('amount')),
                'custody_expenses' => $this->money(Expense::where('source', 'custody')->sum('amount')),
                'treasury_expenses' => $this->money(Expense::where('source', 'treasury')->sum('amount')),
                'direction_figures' => $figures,
                'expenses_by_category' => $expensesByCategory,
                'expenses_by_day' => array_map(fn ($day) => [
                    'date' => $day['date'],
                    'label' => $day['label'],
                    'amount' => $this->money($day['amount']),
                ], $figures['by_day'] ?? []),
                'custodies_by_agents' => $custodiesByAgents,
                'cases_by_researchers' => $casesByResearchers,
            ],
        ]);
    }

    /** GET /api/v1/analytics/researchers */
    public function researchers(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $researchers = User::role('باحث اجتماعي')->with('socialCases')->get();
        $rows = $researchers->map(function ($researcher) {
            $cases = $researcher->socialCases;
            $spent = Expense::where('type', 'social_case')
                ->whereIn('social_case_id', $cases->pluck('id'))->sum('amount');
            $approved = $cases->where('status', 'approved')->count();
            $total = $cases->count();

            return [
                'researcher' => ['id' => $researcher->id, 'name' => $researcher->name],
                'total_cases' => $total,
                'approved' => $approved,
                'rejected' => $cases->where('status', 'rejected')->count(),
                'pending' => $cases->where('status', 'pending')->count(),
                'total_spent' => $this->money($spent),
                'approval_rate' => $total > 0 ? (int) round($approved / $total * 100) : 0,
            ];
        })->sortByDesc('total_spent')->values();

        return response()->json([
            'data' => [
                'summary' => [
                    'researchers_count' => $researchers->count(),
                    'total_cases' => $rows->sum('total_cases'),
                    'approved_cases' => $rows->sum('approved'),
                    'total_spent' => $this->money($rows->sum(fn ($r) => $r['total_spent'])),
                ],
                'researchers' => $rows,
            ],
        ]);
    }

    /** GET /api/v1/reports/governorates (T34) */
    public function governorates(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        [$directionId, $directionItemId] = $this->directionParams($request);
        $governorates = $this->directionStats->governoratesSummary(
            $directionId, $directionItemId, $request->input('date_from'), $request->input('date_to'),
        );

        $grandTotal = [
            'total' => array_sum(array_column($governorates, 'total')),
            'approved' => array_sum(array_column($governorates, 'approved')),
            'pending' => array_sum(array_column($governorates, 'pending')),
            'rejected' => array_sum(array_column($governorates, 'rejected')),
            'expenses_total' => $this->money(array_sum(array_column($governorates, 'expenses_total'))),
        ];
        $grandTotal['average'] = $this->money(
            $grandTotal['total'] > 0 ? $grandTotal['expenses_total'] / $grandTotal['total'] : 0,
        );

        return response()->json(['data' => ['governorates' => $governorates, 'grand_total' => $grandTotal]]);
    }

    /** GET /api/v1/reports/social-case-expenses */
    public function socialCaseExpenses(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $query = Expense::with(['user', 'socialCase', 'category', 'item'])->where('type', 'social_case');
        if ($request->filled('social_case_id')) {
            $query->where('social_case_id', (int) $request->social_case_id);
        }
        if ($request->filled('category_id')) {
            $query->where('expense_category_id', (int) $request->category_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }
        $expenses = $query->get();
        $totalAmount = (float) $expenses->sum('amount');

        $byCategory = $expenses->groupBy(fn ($e) => $e->category?->name ?? 'غير محدد')->map(function ($items) use ($totalAmount) {
            $amount = (float) $items->sum('amount');

            return [
                'category' => $items->first()->category?->name ?? 'غير محدد',
                'count' => $items->count(),
                'total' => $this->money($amount),
                'percentage' => $totalAmount > 0 ? round($amount / $totalAmount * 100, 2) : 0,
            ];
        })->values();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_amount' => $this->money($totalAmount),
                    'count' => $expenses->count(),
                ],
                'by_category' => $byCategory,
                'expenses' => $expenses->map(fn ($e) => [
                    'id' => $e->id,
                    'case' => $e->socialCase?->name,
                    'user' => $e->user?->name,
                    'item' => $e->item?->name,
                    'category' => $e->category?->name,
                    'amount' => $this->money($e->amount),
                    'date' => $e->expense_date?->toIso8601ZuluString(),
                    'description' => $e->description,
                ]),
            ],
        ]);
    }

    /** GET /api/v1/reports/agents-balance (wording T32, formula T10b) */
    public function agentsBalance(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $agents = User::role('مندوب')->get();
        $rows = $agents->map(function ($agent) {
            $custodies = Custody::where('agent_id', $agent->id)
                ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
                ->get();

            if ($custodies->isEmpty()) {
                return null;
            }

            return [
                'agent' => ['id' => $agent->id, 'name' => $agent->name],
                'total_custodies' => $custodies->count(),
                'active_custodies' => $custodies->whereIn('status', ['accepted', 'active', 'partially_returned'])->count(),
                'closed_custodies' => $custodies->where('status', 'closed')->count(),
                'total_received' => $this->money($custodies->sum('amount') + $custodies->sum('transferred_in')),
                'total_spent' => $this->money($custodies->sum('spent')),
                'total_returned' => $this->money($custodies->sum('returned')),
                'transferred_in' => $this->money($custodies->sum('transferred_in')),
                'transferred_out' => $this->money($custodies->sum('transferred_out')),
                'current_balance' => $this->money($custodies->sum(fn ($c) => $c->getRemainingBalance())),
            ];
        })->filter()->sortByDesc('current_balance')->values();

        return response()->json([
            'data' => [
                'grand_totals' => [
                    'total_agents' => $rows->count(),
                    'total_received' => $this->money($rows->sum(fn ($r) => $r['total_received'])),
                    'total_spent' => $this->money($rows->sum(fn ($r) => $r['total_spent'])),
                    'total_returned' => $this->money($rows->sum(fn ($r) => $r['total_returned'])),
                    'total_balance' => $this->money($rows->sum(fn ($r) => $r['current_balance'])),
                ],
                'agents' => $rows,
            ],
        ]);
    }

    /** GET /api/v1/reports/expense-items */
    public function expenseItems(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $query = ExpenseItem::active();
        if ($request->filled('category_id')) {
            $query->where('expense_category_id', (int) $request->category_id);
        }
        $items = $query->ordered()->with('category')->get();

        $rows = $items->map(function ($item) use ($request) {
            $expenses = $item->expenses();
            if ($request->filled('date_from')) {
                $expenses->whereDate('expense_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $expenses->whereDate('expense_date', '<=', $request->date_to);
            }
            $count = (clone $expenses)->count();
            if ($count === 0) {
                return null;
            }
            $total = (float) (clone $expenses)->sum('amount');

            return [
                'item' => $item->name,
                'category' => $item->category?->name,
                'count' => $count,
                'total' => $this->money($total),
                'average' => $this->money($total / $count),
            ];
        })->filter()->values();

        $grandTotal = (float) $rows->sum(fn ($r) => $r['total']);

        return response()->json([
            'data' => [
                'summary' => [
                    'total_amount' => $this->money($grandTotal),
                    'count' => $rows->sum('count'),
                ],
                'items' => $rows->map(function ($r) use ($grandTotal) {
                    $r['percentage'] = $grandTotal > 0 ? round($r['total'] / $grandTotal * 100, 2) : 0;

                    return $r;
                }),
            ],
        ]);
    }

    /** GET /api/v1/reports/categories-analytics — direction analytics (T33 drill-down source) */
    public function categoriesAnalytics(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $expensesFor = function (int $categoryId) use ($dateFrom, $dateTo): Collection {
            $query = Expense::where('expense_category_id', $categoryId);
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            return $query->get();
        };

        $itemExpensesFor = function (ExpenseItem $item) use ($dateFrom, $dateTo): Collection {
            $query = Expense::where('expense_item_id', $item->id);
            if ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            return $query->get();
        };

        $rowFor = function (object $node, int|string $level, ?string $parent, Collection $expenses): array {
            $count = $expenses->count();
            $total = (float) $expenses->sum('amount');
            $lastReviewer = $expenses->whereNotNull('reviewed_at')->sortByDesc('reviewed_at')
                ->first()?->reviewer?->name;

            return [
                'id' => $node->id,
                'name' => $node->name,
                'level' => $level,
                'code' => $node->code,
                'parent_name' => $parent,
                'total_amount' => $this->money($total),
                'expense_count' => $count,
                'average_amount' => $this->money($count > 0 ? $total / $count : 0),
                'reviewed_by' => $lastReviewer,
            ];
        };

        $categoryId = $request->input('category_id');
        if ($categoryId) {
            $selected = ExpenseCategory::find($categoryId);
            $roots = $selected ? collect([$selected]) : collect();
        } else {
            $roots = ExpenseCategory::roots()->active()
                ->with('children.children.items', 'children.items', 'items')->ordered()->get();
        }

        $all = new Collection();
        foreach ($roots as $root) {
            $rootExpenses = $expensesFor($root->id);
            $all->push($rowFor($root, 1, null, $rootExpenses));

            foreach ($root->children as $level2) {
                $level2Expenses = $expensesFor($level2->id);
                $all->push($rowFor($level2, 2, $root->name, $level2Expenses));

                foreach ($level2->children as $level3) {
                    $all->push($rowFor($level3, 3, $level2->name, $expensesFor($level3->id)));
                }
                foreach ($level2->items as $item) {
                    $itemExpenses = $itemExpensesFor($item);
                    if ($itemExpenses->sum('amount') > 0) {
                        $all->push($rowFor($item, 'item', $level2->name, $itemExpenses));
                    }
                }
            }
            foreach ($root->items as $item) {
                $itemExpenses = $itemExpensesFor($item);
                if ($itemExpenses->sum('amount') > 0) {
                    $all->push($rowFor($item, 'item', $root->name, $itemExpenses));
                }
            }
        }

        $all = $all->filter(fn ($d) => (float) $d['total_amount'] > 0)->values();
        $grandTotal = (float) $all->sum(fn ($d) => $d['total_amount']);
        $all = $all->map(function ($d) use ($grandTotal) {
            $d['percentage'] = $grandTotal > 0 ? round($d['total_amount'] / $grandTotal * 100, 2) : 0;

            return $d;
        })->values();

        return response()->json([
            'data' => [
                'grand_total' => $this->money($grandTotal),
                'rows' => $all,
                'roots' => $all->where('level', 1)->values(),
            ],
        ]);
    }

    /** GET /api/v1/reports/reconciliation */
    public function reconciliation(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        $treasuries = Treasury::orderBy('id')->get();
        abort_if($treasuries->isEmpty(), 404, 'لم يتم العثور على خزينة');

        $rows = $treasuries->map(function ($treasury) {
            $sum = fn (string $type, bool $withoutCustody = false) => $this->money(
                TreasuryTransaction::where('treasury_id', $treasury->id)
                    ->when($withoutCustody, fn ($q) => $q->whereNull('custody_id'))
                    ->where('type', $type)->sum('amount'),
            );

            $donations = $sum('donation', true);
            $issued = $sum('custody_out');
            $returned = $sum('custody_return');
            $direct = $sum('expense', true);
            $in = $sum('transfer_in');
            $out = $sum('transfer_out');

            $expected = (float) $donations + (float) $returned + (float) $in - (float) $issued - (float) $direct - (float) $out;
            $actual = (float) $treasury->balance;
            $difference = $actual - $expected;

            return [
                'treasury' => ['id' => $treasury->id, 'name' => $treasury->name],
                'donations' => $donations,
                // إجمالي العهدات الصادرة — التسمية محفوظة كما هي
                'custodies_issued' => $issued,
                'custodies_returned' => $returned,
                'direct_expenses' => $direct,
                'transfers_in' => $in,
                'transfers_out' => $out,
                'actual_balance' => $this->money($actual),
                'expected_balance' => $this->money($expected),
                'difference' => $this->money($difference),
                'is_reconciled' => abs($difference) < 0.01,
            ];
        });

        return response()->json([
            'data' => [
                'treasuries' => $rows,
                'totals' => [
                    'actual_balance' => $this->money($rows->sum(fn ($r) => $r['actual_balance'])),
                    'expected_balance' => $this->money($rows->sum(fn ($r) => $r['expected_balance'])),
                    'difference' => $this->money($rows->sum(fn ($r) => $r['difference'])),
                    'is_reconciled' => $rows->every(fn ($r) => $r['is_reconciled']),
                ],
            ],
        ]);
    }
}
