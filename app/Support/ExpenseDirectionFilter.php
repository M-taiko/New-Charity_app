<?php

namespace App\Support;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * تصفية المصروفات حسب التوجيه المحاسبي (T31) — مشتركة بين قائمة المصروفات (T31)
 * ولوحتي المعلومات والتقارير (T33) وتقرير المحافظات (T34):
 * تُعيد مصروفات العقدة المختارة وكل ما تحتها.
 */
class ExpenseDirectionFilter
{
    /**
     * معرفات الفئة وجميع فئاتها الذرية — استعلام واحد على expense_categories
     * (الشجرة صغيرة ~85 عقدة، فتُبنى الشجرة في الذاكرة بدل استعلامات متكررة).
     * الفئات غير النشطة تبقى في التنقل لأن مصروفات قديمة قد تشير إليها ولا تُخفى أبداً.
     *
     * @return int[]
     */
    public static function descendantCategoryIds(int $categoryId): array
    {
        $children = [];
        foreach (ExpenseCategory::query()->get(['id', 'parent_id']) as $row) {
            $children[$row->parent_id][] = $row->id;
        }

        $ids = [$categoryId];
        $stack = [$categoryId];
        while ($stack) {
            $current = array_pop($stack);
            foreach ($children[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    /**
     * يطبق شرط التوجيه على استعلام المصروفات:
     * - بند محدد: مصروفات ذلك البند بالضبط.
     * - فئة بلا بند: مصروفات تشير إلى الفئة أو أي فئة ذرية، أو بند ينتمي إلى إحداها.
     * (استعلام واحد على expense_categories لكل استدعاء — لا N+1)
     */
    public static function applyToQuery(Builder $query, ?int $categoryId, ?int $itemId): Builder
    {
        if ($itemId !== null) {
            return $query->where('expenses.expense_item_id', $itemId);
        }

        if ($categoryId !== null) {
            $categoryIds = self::descendantCategoryIds($categoryId);

            return $query->where(function (Builder $q) use ($categoryIds) {
                $q->whereIn('expenses.expense_category_id', $categoryIds)
                    ->orWhereHas('item', fn ($itemQuery) => $itemQuery
                        ->whereIn('expense_items.expense_category_id', $categoryIds));
            });
        }

        return $query;
    }
}
