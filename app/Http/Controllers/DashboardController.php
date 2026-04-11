<?php

namespace App\Http\Controllers;

use App\Models\Treasury;
use App\Models\Custody;
use App\Models\Expense;
use App\Models\SocialCase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        // بيانات مشتركة
        $treasury        = Treasury::first();
        $activeCustodies = Custody::whereIn('status', ['accepted', 'active', 'partially_returned'])->count();
        $pendingCases    = SocialCase::where('status', 'pending')->count();
        $todayExpenses   = Expense::whereDate('created_at', today())->sum('amount');
        $totalSpent      = Expense::sum('amount');

        // إجمالي الأصول = رصيد الخزينة + مجموع العهد النشطة
        $totalCustodiesAmount = Custody::whereIn('status', ['accepted', 'active', 'partially_returned'])->sum('amount');
        $totalAssets          = ($treasury ? $treasury->balance : 0) + $totalCustodiesAmount;

        // للمدير/المحاسب فقط: بيانات مع فلتر السنة
        $agentsStats      = [];
        $researchersStats = [];
        $yearStats        = [];
        $selectedYear     = (int) $request->get('year', now()->year);
        $availableYears   = [];

        // للباحث الاجتماعي فقط: إحصائيات خاصة به
        $researcherStats = [];
        if ($user->hasRole('باحث اجتماعي')) {
            $totalCases    = SocialCase::where('researcher_id', $user->id)->count();
            $approvedCases = SocialCase::where('researcher_id', $user->id)->where('status', 'approved')->count();
            $pendingCases  = SocialCase::where('researcher_id', $user->id)->where('status', 'pending')->count();
            $rejectedCases = SocialCase::where('researcher_id', $user->id)->where('status', 'rejected')->count();
            $recentCases   = SocialCase::where('researcher_id', $user->id)->latest()->limit(5)->get();

            $researcherStats = [
                'total_cases'    => $totalCases,
                'approved_cases' => $approvedCases,
                'pending_cases'  => $pendingCases,
                'rejected_cases' => $rejectedCases,
                'approval_rate'  => $totalCases > 0 ? round(($approvedCases / $totalCases) * 100) : 0,
                'recent_cases'   => $recentCases,
            ];
        }

        if (!$user->hasRole('مندوب') && !$user->hasRole('باحث اجتماعي')) {
            // السنوات المتاحة بناءً على بيانات الخزينة والمصروفات
            $firstYear = (int) min(
                Custody::min(\DB::raw('YEAR(created_at)')) ?? now()->year,
                Expense::min(\DB::raw('YEAR(created_at)')) ?? now()->year
            );
            for ($y = now()->year; $y >= $firstYear; $y--) {
                $availableYears[] = $y;
            }

            // إحصائيات السنة المختارة
            $yearStats = [
                'total_custodies_amount' => Custody::whereYear('created_at', $selectedYear)
                    ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
                    ->sum('amount'),
                'total_expenses'         => Expense::whereYear('created_at', $selectedYear)->sum('amount'),
                'total_returned'         => Custody::whereYear('created_at', $selectedYear)->sum('returned'),
                'approved_cases'         => SocialCase::whereYear('created_at', $selectedYear)
                    ->where('status', 'approved')->count(),
            ];

            // تقييم المناديب
            $agents = User::role('مندوب')->get();
            foreach ($agents as $agent) {
                $custodies = Custody::where('agent_id', $agent->id)
                    ->whereYear('created_at', $selectedYear)
                    ->whereIn('status', ['accepted', 'active', 'partially_returned', 'closed'])
                    ->get();

                $totalReceived  = $custodies->sum('amount');
                $totalSpentAmt  = $custodies->sum('spent');
                $totalReturned  = $custodies->sum('returned');
                $expenseCount   = Expense::where('user_id', $agent->id)
                    ->whereYear('created_at', $selectedYear)->count();
                $expenseWithDoc = Expense::where('user_id', $agent->id)
                    ->whereYear('created_at', $selectedYear)
                    ->whereNotNull('attachment')->count();

                // حساب التقييم (من 5 نجوم)
                $rating = $this->calculateAgentRating(
                    $totalReceived, $totalSpentAmt, $totalReturned,
                    $expenseCount, $expenseWithDoc
                );

                $agentsStats[] = [
                    'agent'          => $agent,
                    'total_received' => $totalReceived,
                    'total_spent'    => $totalSpentAmt,
                    'total_returned' => $totalReturned,
                    'remaining'      => $totalReceived - $totalSpentAmt - $totalReturned,
                    'expense_count'  => $expenseCount,
                    'doc_rate'       => $expenseCount > 0 ? round($expenseWithDoc / $expenseCount * 100) : 0,
                    'rating'         => $rating,
                    'custody_count'  => $custodies->count(),
                ];
            }

            // ترتيب تنازلي حسب التقييم
            usort($agentsStats, fn($a, $b) => $b['rating'] <=> $a['rating']);

            // تقييم الباحثين الاجتماعيين
            $researchers = User::role('باحث اجتماعي')->get();
            foreach ($researchers as $researcher) {
                $totalCases    = SocialCase::where('researcher_id', $researcher->id)
                    ->whereYear('created_at', $selectedYear)->count();
                $approvedCases = SocialCase::where('researcher_id', $researcher->id)
                    ->whereYear('created_at', $selectedYear)
                    ->where('status', 'approved')->count();
                $pendingCasesR = SocialCase::where('researcher_id', $researcher->id)
                    ->whereYear('created_at', $selectedYear)
                    ->where('status', 'pending')->count();
                $rejectedCases = SocialCase::where('researcher_id', $researcher->id)
                    ->whereYear('created_at', $selectedYear)
                    ->where('status', 'rejected')->count();

                $approvalRate = $totalCases > 0 ? round($approvedCases / $totalCases * 100) : 0;
                $rating       = $this->calculateResearcherRating($totalCases, $approvedCases, $rejectedCases);

                $researchersStats[] = [
                    'researcher'    => $researcher,
                    'total_cases'   => $totalCases,
                    'approved'      => $approvedCases,
                    'pending'       => $pendingCasesR,
                    'rejected'      => $rejectedCases,
                    'approval_rate' => $approvalRate,
                    'rating'        => $rating,
                ];
            }

            usort($researchersStats, fn($a, $b) => $b['rating'] <=> $a['rating']);
        }

        return view('dashboard.modern', compact(
            'treasury', 'activeCustodies', 'pendingCases', 'todayExpenses', 'totalSpent',
            'totalCustodiesAmount', 'totalAssets',
            'agentsStats', 'researchersStats', 'yearStats', 'selectedYear', 'availableYears',
            'researcherStats'
        ));
    }

    /**
     * حساب تقييم المندوب من 5 نجوم
     * معايير: نسبة التوثيق (40%) + توازن المبالغ (40%) + النشاط (20%)
     */
    private function calculateAgentRating(
        float $received, float $spent, float $returned,
        int $expenseCount, int $docCount
    ): float {
        if ($received == 0 && $expenseCount == 0) {
            return 0;
        }

        $score = 0;

        // معيار توثيق المصروفات (40 نقطة)
        $docRate = $expenseCount > 0 ? ($docCount / $expenseCount) : 0;
        $score  += $docRate * 40;

        // معيار توازن المبالغ: (مصروف + مرجع) / مستلم (40 نقطة)
        if ($received > 0) {
            $accountedRate = min(($spent + $returned) / $received, 1);
            $score        += $accountedRate * 40;
        } else {
            $score += 40; // لا عهدات = محايد
        }

        // معيار النشاط: عدد المصروفات (20 نقطة)
        $activityScore = min($expenseCount / 10, 1); // 10+ مصروفات = 20 نقطة كاملة
        $score        += $activityScore * 20;

        // تحويل من 100 إلى 5 نجوم
        return round($score / 20, 1);
    }

    /**
     * حساب تقييم الباحث من 5 نجوم
     * معايير: معدل القبول (60%) + النشاط (40%)
     */
    private function calculateResearcherRating(
        int $total, int $approved, int $rejected
    ): float {
        if ($total == 0) {
            return 0;
        }

        $score = 0;

        // معدل القبول (60 نقطة)
        $approvalRate = $approved / $total;
        $score       += $approvalRate * 60;

        // نسبة الرفض المنخفضة تزيد التقييم (20 نقطة)
        $rejectionRate = $rejected / $total;
        $score        += (1 - $rejectionRate) * 20;

        // النشاط: عدد الحالات (20 نقطة)
        $activityScore = min($total / 10, 1);
        $score        += $activityScore * 20;

        return round($score / 20, 1);
    }
}
