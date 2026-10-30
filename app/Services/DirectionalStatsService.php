<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\SocialCase;
use App\Support\ExpenseDirectionFilter;
use App\Support\EgyptianGovernorates;

/**
 * الأرقام المتأثرة بالتوجيه المحاسبي (T33) — مشتركة بين لوحة المعلومات المالية
 * وصفحة التقارير، وتُقدَّم أيضاً عبر نقطة JSON واحدة لتحديث الصفحة دون مغادرتها.
 * كل تصفية التوجيه تمر حصراً عبر المساعد المشترك من T31.
 *
 * قاعدة النوافذ: اليوم/الشهر/السنة والإجمالي الكلي يتبعون التوجيه فقط،
 * ورقم الفترة والرسوم يتبعون التوجيه + نطاق التاريخ (حيث توجد نطاقات).
 */
class DirectionalStatsService
{
    private function directionalQuery(?int $categoryId, ?int $itemId)
    {
        $query = Expense::query();
        return ExpenseDirectionFilter::applyToQuery($query, $categoryId, $itemId);
    }

    private function rangedQuery(?int $categoryId, ?int $itemId, ?string $dateFrom, ?string $dateTo)
    {
        $query = $this->directionalQuery($categoryId, $itemId);
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        return $query;
    }

    /**
     * كل الأرقام المتأثرة بالتوجيه (مبالغ كأرقام عشرية بخانتين)
     */
    public function figures(?int $categoryId, ?int $itemId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        // نوافذ ثابتة المعنى: تتبع التوجيه فقط (لا تتأثر بنطاق التاريخ)
        $today = round((float) $this->directionalQuery($categoryId, $itemId)
            ->whereDate('created_at', today())->sum('amount'), 2);
        $thisMonth = round((float) $this->directionalQuery($categoryId, $itemId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->sum('amount'), 2);
        $thisYear = round((float) $this->directionalQuery($categoryId, $itemId)
            ->whereYear('created_at', now()->year)->sum('amount'), 2);
        $grandTotal = round((float) $this->directionalQuery($categoryId, $itemId)->sum('amount'), 2);

        // رقم الفترة: التوجيه + نطاق التاريخ
        $periodTotal = round((float) $this->rangedQuery($categoryId, $itemId, $dateFrom, $dateTo)->sum('amount'), 2);

        // توزيع النوع (حالة اجتماعية / عام)
        $byType = $this->rangedQuery($categoryId, $itemId, $dateFrom, $dateTo)
            ->selectRaw('type, COALESCE(SUM(amount),0) AS total')
            ->groupBy('type')->pluck('total', 'type');
        $typeFigures = [
            'social_case' => round((float) ($byType['social_case'] ?? 0), 2),
            'general' => round((float) ($byType['general'] ?? 0), 2),
        ];

        // آخر 7 أيام
        $byDay = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $amount = round((float) $this->rangedQuery($categoryId, $itemId, $dateFrom, $dateTo)
                ->whereDate('created_at', $day->toDateString())->sum('amount'), 2);
            $byDay[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('d-m'),
                'amount' => $amount,
            ];
        }

        // شهري: مجموع كل شهر عبر السنوات (بنفس دلالات رسم الصفحة الحالية) — للتوجيه فقط
        $byMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $byMonth[] = round((float) $this->directionalQuery($categoryId, $itemId)
                ->whereMonth('created_at', $m)->sum('amount'), 2);
        }

        // أعلى المناديب بالمصروفات (رسم المناديب يُبنى من المصروفات)
        $byAgent = $this->directionalQuery($categoryId, $itemId)
            ->join('users', 'users.id', '=', 'expenses.user_id')
            ->selectRaw('users.id, users.name, COALESCE(SUM(expenses.amount),0) AS total, COUNT(expenses.id) AS cnt')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'amount' => round((float) $row->total, 2),
                'count' => (int) $row->cnt,
            ])->values()->all();

        // T34: بطاقة الحالات حسب المحافظة — المصروفات تتبع التوجيه/الفترة، والعدد ثابت
        $governoratesTop = collect($this->governoratesSummary($categoryId, $itemId, $dateFrom, $dateTo))
            ->sortByDesc('expenses_total')
            ->take(5)
            ->values()->all();

        return [
            'today' => $today,
            'this_month' => $thisMonth,
            'this_year' => $thisYear,
            'grand_total' => $grandTotal,
            'period_total' => $periodTotal,
            'by_type' => $typeFigures,
            'by_day' => $byDay,
            'by_month' => $byMonth,
            'by_agent' => $byAgent,
            'governorates_top' => $governoratesTop,
        ];
    }

    /**
     * T34: ملخص الحالات حسب المحافظة — صف لكل محافظة (عدد الحالات بحالاتها
     * وإجمالي المصروف عليها ومتوسطه). التجميع بعد تسوية القيم القديمة، والقيم
     * الفارغة تظهر كـ"غير محدد" وغير المطابقة كصفوف "قيمة غير معيارية".
     * المصروفات تُحسب عبر المساعد المشترك (توجيه) ونطاق التاريخ، والأعداد ثابتة.
     */
    public function governoratesSummary(?int $categoryId, ?int $itemId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $cases = SocialCase::query()->get(['id', 'city', 'status']);

        // تجميع الحالات بعد التسوية
        $groups = [];
        $groupCaseIds = [];
        foreach ($cases as $case) {
            $city = trim((string) $case->city);
            if ($city === '') {
                $key = '';
                $meta = ['label' => 'غير محدد', 'standard' => true, 'order' => 9999];
            } else {
                $canonical = EgyptianGovernorates::match($city);
                if ($canonical !== null) {
                    $key = EgyptianGovernorates::normalize($canonical);
                    $order = array_search($canonical, EgyptianGovernorates::LIST, true);
                    $meta = ['label' => $canonical, 'standard' => true, 'order' => $order];
                } else {
                    $key = EgyptianGovernorates::normalize($city);
                    $meta = ['label' => $city, 'standard' => false, 'order' => 9000];
                }
            }

            $groups[$key] = $groups[$key] ?? $meta + [
                'total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0,
            ];
            $groups[$key]['total']++;
            if (in_array($case->status, ['approved', 'pending', 'rejected'], true)) {
                $groups[$key][$case->status]++;
            }
            $groupCaseIds[$key][] = $case->id;
        }

        if ($groups === []) {
            return [];
        }

        // مجموعات المصروفات لكل حالة استعداداً للتوزيع على المحافظات (استعلام واحد)
        $expenseQuery = Expense::query();
        ExpenseDirectionFilter::applyToQuery($expenseQuery, $categoryId, $itemId);
        if ($dateFrom) {
            $expenseQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $expenseQuery->whereDate('created_at', '<=', $dateTo);
        }
        $sumsByCase = (clone $expenseQuery)->whereNotNull('social_case_id')
            ->selectRaw('social_case_id, COALESCE(SUM(amount),0) AS total')
            ->groupBy('social_case_id')
            ->pluck('total', 'social_case_id');

        // بناء الصفوف: المحافظات المعيارية بترتيب القائمة، ثم غير المعيارية، ثم "غير محدد"
        $rows = [];
        foreach ($groups as $key => $group) {
            $expensesTotal = 0.0;
            foreach ($groupCaseIds[$key] as $caseId) {
                $expensesTotal += (float) ($sumsByCase[$caseId] ?? 0);
            }
            $expensesTotal = round($expensesTotal, 2);
            $rows[] = [
                'label' => $group['label'],
                'standard' => $group['standard'],
                'total' => $group['total'],
                'approved' => $group['approved'],
                'pending' => $group['pending'],
                'rejected' => $group['rejected'],
                'expenses_total' => $expensesTotal,
                'average' => $group['total'] > 0 ? round($expensesTotal / $group['total'], 2) : 0.0,
                '_order' => $group['order'],
            ];
        }

        usort($rows, fn ($a, $b) => [$a['standard'] === false, $a['_order']] <=> [$b['standard'] === false, $b['_order']]);

        // إزالة مفتاح الترتيب الداخلي
        return array_map(fn ($row) => array_diff_key($row, ['_order' => null]), $rows);
    }

    /**
     * مستكشف التوجيه: أبناء العقدة الحالية (أو الجذور في البداية) مع مبالغها،
     * والبنود كآخر مستوى عندما لا يكون للفئة أبناء نشطون.
     * التنقل بالفئات النشطة فقط، والمبالغ لا تخفي مصروفات فئات غير نشطة يشير إليها الابن.
     */
    public function explorer(?int $categoryId, ?int $itemId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $node = $categoryId ? ExpenseCategory::find($categoryId) : null;

        $childrenQuery = $node
            ? $node->children()->active()->ordered()
            : ExpenseCategory::roots()->active()->ordered();
        $children = $childrenQuery->get(['id', 'name']);

        $childRows = [];
        foreach ($children as $child) {
            $amount = round((float) $this->rangedQuery($child->id, null, $dateFrom, $dateTo)->sum('amount'), 2);
            $childRows[] = ['id' => $child->id, 'name' => $child->name, 'amount' => $amount, 'is_item' => false];
        }

        // مجموع العقدة نفسها (يشمل كل ما تحتها) والمصروف "المباشر" عليها:
        // مباشر = مجموع العقدة − مجاميع الأبناء، حتى يبقى (الأبناء + المباشر) = مجموع العقدة
        $nodeTotal = $node
            ? round((float) $this->rangedQuery($node->id, null, $dateFrom, $dateTo)->sum('amount'), 2)
            : 0.0;
        $direct = $node ? round($nodeTotal - array_sum(array_column($childRows, 'amount')), 2) : 0.0;
        if ($direct < 0) {
            $direct = 0.0;
        }

        // البنود كآخر مستوى (تُعرض حين لا أبناء نشطون للعقدة، أو كبديل حين لا فئة أصلاً؟ الجذور دائماً فئات)
        $itemRows = [];
        if ($node && $children->isEmpty()) {
            foreach ($node->items()->active()->ordered()->get(['id', 'name']) as $item) {
                $amount = round((float) $this->rangedQuery(null, $item->id, $dateFrom, $dateTo)->sum('amount'), 2);
                $itemRows[] = ['id' => $item->id, 'name' => $item->name, 'amount' => $amount, 'is_item' => true];
            }
        }

        // مسار التنقل (السلسلة من الجذر إلى العقدة الحالية)
        $trail = [];
        $current = $node;
        while ($current) {
            array_unshift($trail, ['id' => $current->id, 'name' => $current->name]);
            $current = $current->parent;
        }

        return [
            'current' => $node ? ['id' => $node->id, 'name' => $node->name] : null,
            'trail' => $trail,
            'children' => $childRows,
            'direct_amount' => $direct,
            'items' => $itemRows,
            'selected_item' => $itemId,
        ];
    }
}
