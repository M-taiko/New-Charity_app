<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\ExpenseDirectionFilter;

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
        ];
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
