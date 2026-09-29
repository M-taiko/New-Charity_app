<?php

namespace App\Support;

use App\Models\Expense;
use Illuminate\Support\Facades\Storage;

class AttachmentFileCleanup
{
    /**
     * حذف ملف مرفق مصروف من القرص فقط إذا لم يعد أي سجل مصروف آخر يشير إلى نفس المسار (T24)
     * تُستدعى فقط لحظة تطبيق التغيير فعلياً (حفظ مباشر أو موافقة على طلب تعديل) — أبداً عند إنشاء الطلب
     */
    public static function deleteIfUnreferenced(?string $path, ?int $ignoreExpenseId = null): bool
    {
        if (!$path) {
            return false;
        }

        $stillReferenced = Expense::where('attachment', $path)
            ->when($ignoreExpenseId, fn ($q) => $q->where('id', '!=', $ignoreExpenseId))
            ->exists();

        if ($stillReferenced) {
            return false;
        }

        try {
            return Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
