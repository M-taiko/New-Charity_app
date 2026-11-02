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
    public function show(Request $request): JsonResponse
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
