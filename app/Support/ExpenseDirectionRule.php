<?php

namespace App\Support;

use App\Models\ExpenseCategory;
use Illuminate\Validation\ValidationException;

/**
 * قاعدة اكتمال التوجيه المحاسبي حتى آخر مستوى (T30) — تطبق في كل مسارات الحفظ:
 * - للفئة المختارة فئات أبناء نشطة → التوجيه غير مكتمل: يجب اختيار مستوى أعمق
 * - لا أبناء ولها بنود نشطة → يجب اختيار بند (قاعدة T2)
 * - لا أبناء ولا بنود (جذر طرفي أو فئة مستوى ثالث بلا بنود) → التوجيه مكتمل
 */
class ExpenseDirectionRule
{
    /** رسالة نقص مستوى أعمق (فئة لها أبناء) */
    public const MSG_NEXT_LEVEL = 'اختر المستوى التالي من التوجيه المحاسبي';

    /** رسالة نقص البند (رسالة T2 كما هي) */
    public const MSG_ITEM = 'يجب اختيار التوجيه النهائي (المستوى الرابع) لأن هذه الفئة تحتوي على بنود';

    /**
     * يتحقق أن الفئة/البند المرسلين يشكلان توجيهاً مكتملاً، وإلا يرمي ValidationException
     * برسالة عربية تحت مفتاح الحقل المسؤول. غياب الفئة أو عدم وجودها تلتقطه قاعدتا
     * required/exists في التحقيق العادي فلا تكرر هنا.
     */
    public static function assertComplete(?int $categoryId, ?int $itemId): void
    {
        if ($categoryId === null) {
            return;
        }

        $category = ExpenseCategory::find($categoryId);
        if (!$category) {
            return;
        }

        if ($category->hasActiveChildren()) {
            throw ValidationException::withMessages([
                'expense_category_id' => self::MSG_NEXT_LEVEL,
            ]);
        }

        if ($itemId === null && $category->hasActiveItems()) {
            throw ValidationException::withMessages([
                'expense_item_id' => self::MSG_ITEM,
            ]);
        }
    }
}
