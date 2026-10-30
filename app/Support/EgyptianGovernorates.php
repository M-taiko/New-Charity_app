<?php

namespace App\Support;

/**
 * قائمة المحافظات المصرية (T34) — تُخزَّن الأسماء العربية في عمود city كما هي.
 * التسوية تُستخدم للمقارنة والتجميع فقط ولا تعيد كتابة أي شيء في قاعدة البيانات:
 * "القاهره" و"القاهرة" تسقطان في الصف نفسه، والقيمة الفارغة تظهر كـ"غير محدد".
 */
class EgyptianGovernorates
{
    public const LIST = [
        'القاهرة', 'الجيزة', 'الإسكندرية', 'القليوبية', 'الشرقية', 'الدقهلية',
        'البحيرة', 'المنوفية', 'الغربية', 'كفر الشيخ', 'دمياط', 'بورسعيد',
        'الإسماعيلية', 'السويس', 'شمال سيناء', 'جنوب سيناء', 'الفيوم', 'بني سويف',
        'المنيا', 'أسيوط', 'سوهاج', 'قنا', 'الأقصر', 'أسوان', 'البحر الأحمر',
        'الوادي الجديد', 'مطروح',
    ];

    /**
     * تسوية للمقارنة: حذف الفراغات الزائدة والتشكيل، توحيد أشكال الألف،
     * والتاء المربوطة/المفتوحة، والألف المقصورة/الياء
     */
    public static function normalize(string $value): string
    {
        $value = trim($value);
        // إزالة التشكيل (فتحة/ضمة/كسرة/شدة/سكون/تنوين/مد)
        $value = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $value) ?? $value;
        $value = str_replace(['أ', 'إ', 'آ'], 'ا', $value);
        $value = str_replace('ة', 'ه', $value);
        $value = str_replace('ى', 'ي', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * الاسم المعياري المكافئ لقيمة ما، أو null إن لم تطابق أي محافظة
     */
    public static function match(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = self::normalize($value);
        foreach (self::LIST as $governorate) {
            if (self::normalize($governorate) === $normalized) {
                return $governorate;
            }
        }

        return null;
    }
}
