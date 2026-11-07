<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\DashboardController as BladeDashboardController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Same numbers as the Blade dashboard, served as JSON. All computation stays
 * in the parent controller so the two interfaces can never drift.
 */
final class DashboardController extends BladeDashboardController
{
    /** the accepted range values (T6-60): today / 7d / 30d / month / year / custom */
    public const RANGES = ['today', '7d', '30d', 'month', 'year', 'custom'];

    /**
     * GET /api/v1/dashboard?range=today|7d|30d|month|year|custom&from=&to=
     * All KPIs and series in one request, cached ~60 s per user/role (T6-60).
     */
    public function show(Request $request): JsonResponse
    {
        $range = in_array((string) $request->query('range'), self::RANGES, true) ? (string) $request->query('range') : '30d';

        return cache()->remember("dashboard.{$request->user()->id}.{$range}", 60, function () use ($request, $range) {
            return $this->dashboardPayload($request, $range);
        });
    }

    /**
     * GET /api/v1/dashboard/money-flow — the heavier chart series, loaded
     * lazily by the widgets that need them (T6-60 backend).
     */
    public function moneyFlow(Request $request): JsonResponse
    {
        $months = collect(range(5, 0))->map(function ($back) {
            $now = now()->subMonths($back);
            $start = $now->copy()->startOfMonth();
            $end = $now->copy()->endOfMonth();

            return [
                'month' => $start->format('Y-m'),
                'income' => number_format((float) \App\Models\TreasuryTransaction::where('type', 'like', '%in%')->whereBetween('transaction_date', [$start, $end])->sum('amount'), 2, '.', ''),
                'expenses' => number_format((float) \App\Models\Expense::whereBetween('expense_date', [$start, $end])->sum('amount'), 2, '.', ''),
            ];
        });

        $statusBreakdown = \App\Models\Custody::selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount + transferred_in - spent - returned), 0) as total')
            ->groupBy('status')->pluck('count', 'status');

        return response()->json(['data' => [
            'income_vs_expenses' => $months->values(),
            'custody_status_breakdown' => $statusBreakdown,
            'treasury_balance' => number_format((float) (\App\Models\Treasury::sum('balance') ?? 0), 2, '.', ''),
        ]]);
    }

    private function dashboardPayload(Request $request, string $range): JsonResponse
    {
        $data = $this->buildDashboardData($request);

        $user = $request->user();
        $view = 'finance';
        if ($user->hasRole('مندوب') && ! $user->hasAnyRole(['مدير', 'محاسب'])) {
            $view = 'agent';
        } elseif ($user->hasRole('باحث اجتماعي') && ! $user->hasAnyRole(['مدير', 'محاسب'])) {
            $view = 'researcher';
        }

        return response()->json([
            'data' => [
                'view' => $view,
                'range' => $range,
                'treasury_balance' => number_format((float) ($data['treasury']->balance ?? 0), 2, '.', ''),
                'active_custodies' => (int) $data['activeCustodies'],
                'pending_cases' => (int) $data['pendingCases'],
                'today_expenses' => number_format((float) $data['todayExpenses'], 2, '.', ''),
                'total_spent' => number_format((float) $data['totalSpent'], 2, '.', ''),
                'total_custodies_amount' => number_format((float) $data['totalCustodiesAmount'], 2, '.', ''),
                // T35: إجمالي الخزينة والعهد (كانت تسمى الأصول الكلية)
                'total_treasury_and_custodies' => number_format((float) $data['totalAssets'], 2, '.', ''),
                'agent_stats' => $this->mapAgentStats($data['agentStats'] ?? []),
                'researcher_stats' => $this->mapResearcherStats($data['researcherStats'] ?? []),
                'agents_evaluation' => array_map(fn ($row) => [
                    'agent' => ['id' => $row['agent']->id, 'name' => $row['agent']->name],
                    'total_received' => number_format((float) $row['total_received'], 2, '.', ''),
                    'total_spent' => number_format((float) $row['total_spent'], 2, '.', ''),
                    'total_returned' => number_format((float) $row['total_returned'], 2, '.', ''),
                    'remaining' => number_format((float) $row['remaining'], 2, '.', ''),
                    'expense_count' => (int) $row['expense_count'],
                    'doc_rate' => (int) $row['doc_rate'],
                    'rating' => (float) $row['rating'],
                    'custody_count' => (int) $row['custody_count'],
                ], $data['agentsStats'] ?? []),
                'researchers_evaluation' => array_map(fn ($row) => [
                    'researcher' => ['id' => $row['researcher']->id, 'name' => $row['researcher']->name],
                    'total_cases' => (int) $row['total_cases'],
                    'approved' => (int) $row['approved'],
                    'pending' => (int) $row['pending'],
                    'rejected' => (int) $row['rejected'],
                    'approval_rate' => (int) $row['approval_rate'],
                    'rating' => (float) $row['rating'],
                ], $data['researchersStats'] ?? []),
                'year_stats' => [
                    'selected_year' => (int) ($data['selectedYear'] ?? now()->year),
                    'available_years' => ! empty($data['availableYears']) ? array_values($data['availableYears']) : [now()->year],
                    'total_custodies_amount' => number_format((float) (($data['yearStats']['total_custodies_amount'] ?? 0) ?? 0), 2, '.', ''),
                    'total_expenses' => number_format((float) (($data['yearStats']['total_expenses'] ?? 0) ?? 0), 2, '.', ''),
                    'total_returned' => number_format((float) (($data['yearStats']['total_returned'] ?? 0) ?? 0), 2, '.', ''),
                    'approved_cases' => (int) ((($data['yearStats']['approved_cases'] ?? 0) ?? 0)),
                ],
                'direction_figures' => $data['directionFigures'],
            ],
        ]);
    }

    /**
     * GET /api/v1/dashboard/performance?range=… (T7-08) — the heavy widget
     * data, loaded lazily by the widgets that need it: KPI sparklines with a
     * delta vs the previous period, custody aging buckets, the case funnel
     * with its approval-rate gauge, spending by direction, and the HR variant
     * (attendance today, pending salaries, average KPI achievement).
     */
    public function performance(Request $request): JsonResponse
    {
        $range = in_array((string) $request->query('range'), self::RANGES, true) ? (string) $request->query('range') : '30d';
        [$from, $to] = $this->rangeBounds($range);
        $prevFrom = (clone $from)->subDays($to->diffInDays($from) + 1)->startOfDay();
        $prevTo = (clone $from)->subDay()->endOfDay();

        return cache()->remember("dashboard.perf.{$range}", 60, fn () => response()->json(['data' => [
            'range' => $range,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'kpis' => $this->kpiSparks($from, $to, $prevFrom, $prevTo),
            'aging' => $this->custodyAging(),
            'case_funnel' => $this->caseFunnel(),
            'approval_rate' => $this->approvalRate(),
            'spending_by_direction' => $this->spendingByDirection($from, $to),
            'hr' => $this->hrSummary(),
        ]]));
    }

    /**
     * GET /api/v1/dashboard/activity (T7-08) — the lazy activity block feed:
     * the latest events, own events for non-managers.
     */
    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();
        $seesAll = $user->can('view_all_records') || $user->can('manage_treasury');

        $query = \App\Models\ActivityLog::with('user')->latest('created_at')->limit(20);
        if (! $seesAll) {
            $query->where('user_id', $user->id);
        }

        return response()->json(['data' => $query->get()->map(fn ($log) => [
            'id' => $log->id,
            'event' => $log->event,
            'description' => $log->description,
            'user' => $log->user?->name,
            'created_at' => $log->created_at?->toIso8601ZuluString(),
        ])->values()]);
    }

    /** @return array{\Carbon\CarbonInterface, \Carbon\CarbonInterface} */
    private function rangeBounds(string $range): array
    {
        return match ($range) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '7d' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
        };
    }

    /** one KPI's value, previous-period value, % delta and a daily sparkline */
    private function kpiSparks($from, $to, $prevFrom, $prevTo): array
    {
        $sum = fn ($query, $dateColumn) => (clone $query)->whereBetween($dateColumn, [$from, $to])->sum('amount');
        $prevSum = fn ($query, $dateColumn) => (clone $query)->whereBetween($dateColumn, [$prevFrom, $prevTo])->sum('amount');

        $expenses = \App\Models\Expense::query();
        $income = \App\Models\TreasuryTransaction::where('type', 'like', '%in%');
        $custodies = \App\Models\Custody::query();
        $cases = \App\Models\SocialCase::query();

        $spark = function ($query, string $dateColumn) use ($from, $to): array {
            $days = collect();
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $days->push(['d' => $d->toDateString(), 'v' => 0]);
            }
            $keyed = (clone $query)
                ->whereBetween($dateColumn, [$from, $to])
                ->selectRaw("DATE({$dateColumn}) as day, COALESCE(SUM(CASE WHEN 1=1 THEN 1 ELSE 0 END), 0) as c")
                ->groupBy('day')
                ->pluck('c', 'day');
            // count-per-day series (KPIs are counts except money ones handled below)
            return $days->map(function ($row) use ($keyed) {
                $row['v'] = (int) ($keyed[$row['d']] ?? 0);
                return $row;
            })->values()->all();
        };

        $moneySpark = function ($query, string $dateColumn) use ($from, $to): array {
            $days = collect();
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $days->push(['d' => $d->toDateString(), 'v' => '0.00']);
            }
            $keyed = (clone $query)
                ->whereBetween($dateColumn, [$from, $to])
                ->selectRaw("DATE({$dateColumn}) as day, COALESCE(SUM(amount), 0) as total")
                ->groupBy('day')
                ->pluck('total', 'day');
            return $days->map(function ($row) use ($keyed) {
                $row['v'] = number_format((float) ($keyed[$row['d']] ?? 0), 2, '.', '');
                return $row;
            })->values()->all();
        };

        $delta = fn ($current, $previous) => $previous > 0 ? (int) round((($current - $previous) / $previous) * 100) : ($current > 0 ? 100 : 0);

        return [
            'expenses' => [
                'value' => number_format((float) $sum($expenses, 'expense_date'), 2, '.', ''),
                'prev' => number_format((float) $prevSum($expenses, 'expense_date'), 2, '.', ''),
                'delta' => $delta((float) $sum($expenses, 'expense_date'), (float) $prevSum($expenses, 'expense_date')),
                'spark' => $moneySpark(\App\Models\Expense::query(), 'expense_date'),
            ],
            'income' => [
                'value' => number_format((float) $sum($income, 'transaction_date'), 2, '.', ''),
                'prev' => number_format((float) $prevSum($income, 'transaction_date'), 2, '.', ''),
                'delta' => $delta((float) $sum($income, 'transaction_date'), (float) $prevSum($income, 'transaction_date')),
                'spark' => $moneySpark(\App\Models\TreasuryTransaction::where('type', 'like', '%in%'), 'transaction_date'),
            ],
            'custodies' => [
                'value' => (int) (clone $custodies)->whereBetween('created_at', [$from, $to])->count(),
                'prev' => (int) (clone $custodies)->whereBetween('created_at', [$prevFrom, $prevTo])->count(),
                'delta' => 0,
                'spark' => $spark(\App\Models\Custody::query(), 'created_at'),
            ],
            'cases' => [
                'value' => (int) (clone $cases)->whereBetween('created_at', [$from, $to])->count(),
                'prev' => (int) (clone $cases)->whereBetween('created_at', [$prevFrom, $prevTo])->count(),
                'delta' => 0,
                'spark' => $spark(\App\Models\SocialCase::query(), 'created_at'),
            ],
        ];
    }

    /** custody aging buckets: days an OPEN custody has been open (T7-08) */
    private function custodyAging(): array
    {
        $buckets = ['0-30' => [0, 0.0], '31-60' => [0, 0.0], '61-90' => [0, 0.0], '90+' => [0, 0.0]];

        \App\Models\Custody::whereIn('status', ['accepted', 'active', 'partially_returned'])
            ->selectRaw('COALESCE(DATEDIFF(NOW(), created_at), 0) as age, amount')
            ->get()
            ->each(function ($c) use (&$buckets) {
                $age = (int) $c->age;
                $key = $age <= 30 ? '0-30' : ($age <= 60 ? '31-60' : ($age <= 90 ? '61-90' : '90+'));
                $buckets[$key][0]++;
                $buckets[$key][1] += (float) $c->amount;
            });

        return collect($buckets)->map(fn ($v, $k) => [
            'bucket' => $k,
            'count' => $v[0],
            'total' => number_format($v[1], 2, '.', ''),
        ])->values()->all();
    }

    /** case funnel stages (T7-08) */
    private function caseFunnel(): array
    {
        $counts = \App\Models\SocialCase::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            ['stage' => 'new', 'count' => (int) ($counts['new'] ?? 0)],
            ['stage' => 'pending', 'count' => (int) ($counts['pending'] ?? 0)],
            ['stage' => 'approved', 'count' => (int) ($counts['approved'] ?? 0)],
            ['stage' => 'rejected', 'count' => (int) ($counts['rejected'] ?? 0)],
        ];
    }

    /** overall case approval rate, % (T7-08 gauge) */
    private function approvalRate(): int
    {
        $total = \App\Models\SocialCase::count();
        if ($total === 0) {
            return 0;
        }

        return (int) round(((int) \App\Models\SocialCase::where('status', 'approved')->count() / $total) * 100);
    }

    /** top expense categories by spend in range (T7-08 donut) */
    private function spendingByDirection($from, $to): array
    {
        return \App\Models\Expense::whereBetween('expense_date', [$from, $to])
            ->join('expense_categories as ec', 'ec.id', '=', 'expenses.expense_category_id')
            ->groupBy('ec.name')
            ->selectRaw('ec.name as label, COALESCE(SUM(expenses.amount), 0) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => number_format((float) $row->total, 2, '.', '')])
            ->values()
            ->all();
    }

    /** HR variant summary: attendance today, pending salaries, KPI achievement (T7-08) */
    private function hrSummary(): array
    {
        $today = now()->toDateString();

        $attendance = \App\Models\AttendanceRecord::whereDate('date', $today)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $pendingSalaries = \App\Models\SalaryCalculation::whereIn('status', ['draft', 'calculated', 'approved']);

        $kpiAvgValue = \App\Models\KpiMetric::exists()
            ? (int) round((float) \App\Models\KpiMetric::selectRaw('COALESCE(AVG(LEAST(actual_value / NULLIF(target_value, 0), 2) * 100), 0) as achievement')->value('achievement'))
            : 0;

        return [
            'attendance_today' => [
                'present' => (int) ($attendance['present'] ?? 0),
                'absent' => (int) ($attendance['absent'] ?? 0),
                'late' => (int) ($attendance['late'] ?? 0),
                'leave' => (int) ($attendance['leave'] ?? 0),
            ],
            'pending_salaries' => [
                'count' => (int) (clone $pendingSalaries)->count(),
                'total' => number_format((float) (clone $pendingSalaries)->sum('final_salary'), 2, '.', ''),
            ],
            'kpi_achievement' => $kpiAvgValue,
        ];
    }

    /**
     * GET /api/v1/dashboard/layout — the user's widget order/visibility (T6-60).
     * POST saves it. Stored per user; the SPA seeds from the default list.
     */
    public function layout(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $key = "dashboard.layout.{$userId}";

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'widgets' => 'present|array',
                'widgets.*' => 'string|max:64',
            ]);
            cache()->put($key, $validated['widgets'], now()->addYear());

            return response()->json(['data' => ['widgets' => $validated['widgets']]]);
        }

        return response()->json(['data' => [
            'widgets' => cache()->get($key),
        ]]);
    }

    private function mapAgentStats(array $stats): array
    {
        if ($stats === []) {
            return null;
        }

        return [
            'received' => number_format((float) $stats['received'], 2, '.', ''),
            'spent' => number_format((float) $stats['spent'], 2, '.', ''),
            'returned' => number_format((float) $stats['returned'], 2, '.', ''),
            'transferred_out' => number_format((float) $stats['transferred_out'], 2, '.', ''),
            // T26: المبلغ المجمّد لتحويل معلق يظهر في أرقام المندوب
            'frozen' => number_format((float) $stats['pending_transfer_out'] ?? 0, 2, '.', ''),
            'pending_return' => number_format((float) $stats['pending_return'], 2, '.', ''),
            'remaining' => number_format((float) $stats['remaining'], 2, '.', ''),
        ];
    }

    private function mapResearcherStats(array $stats): array
    {
        if ($stats === []) {
            return null;
        }

        return [
            'total_cases' => (int) $stats['total_cases'],
            'approved_cases' => (int) $stats['approved_cases'],
            'pending_cases' => (int) $stats['pending_cases'],
            'rejected_cases' => (int) $stats['rejected_cases'],
            'approval_rate' => (int) $stats['approval_rate'],
            'recent_cases' => collect($stats['recent_cases'] ?? [])->map(fn ($case) => [
                'id' => $case->id,
                'name' => $case->name,
                'status' => $case->status,
                'created_at' => $case->created_at?->toIso8601ZuluString(),
            ])->values(),
        ];
    }
}
