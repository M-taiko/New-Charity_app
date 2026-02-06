<?php

namespace App\Services;

class StatusLabelService
{
    /**
     * Get status label with badge HTML
     */
    public static function label($status, $type = 'custody'): string
    {
        $labels = self::getLabels($type);
        return $labels[$status] ?? '';
    }

    /**
     * Get all status labels for a given type
     */
    public static function getLabels($type): array
    {
        return match ($type) {
            'custody' => self::custodyLabels(),
            'social_case' => self::socialCaseLabels(),
            'transaction' => self::transactionLabels(),
            'expense' => self::expenseLabels(),
            default => [],
        };
    }

    /**
     * Custody status labels
     */
    private static function custodyLabels(): array
    {
        return [
            'pending' => '<span class="badge bg-warning">قيد الانتظار</span>',
            'accepted' => '<span class="badge bg-success">مقبول</span>',
            'rejected' => '<span class="badge bg-danger">مرفوض</span>',
            'partially_returned' => '<span class="badge bg-info">مرتجع جزئياً</span>',
            'pending_return' => '<span class="badge bg-primary">في انتظار الإرجاع</span>',
            'closed' => '<span class="badge bg-secondary">مغلق</span>',
        ];
    }

    /**
     * Social case status labels
     */
    private static function socialCaseLabels(): array
    {
        return [
            'open' => '<span class="badge bg-primary">مفتوح</span>',
            'in_progress' => '<span class="badge bg-info">قيد المعالجة</span>',
            'closed' => '<span class="badge bg-success">مغلق</span>',
            'rejected' => '<span class="badge bg-danger">مرفوض</span>',
        ];
    }

    /**
     * Transaction type labels
     */
    private static function transactionLabels(): array
    {
        return [
            'donation' => '<span class="badge bg-success">تبرع</span>',
            'expense' => '<span class="badge bg-danger">مصروف</span>',
            'custody_out' => '<span class="badge bg-info">عهدة صرف</span>',
            'custody_return' => '<span class="badge bg-primary">عهدة إرجاع</span>',
        ];
    }

    /**
     * Expense type labels
     */
    private static function expenseLabels(): array
    {
        return [
            'food' => '<span class="badge bg-info">طعام</span>',
            'medical' => '<span class="badge bg-danger">طبي</span>',
            'education' => '<span class="badge bg-primary">تعليم</span>',
            'housing' => '<span class="badge bg-warning">سكن</span>',
            'transport' => '<span class="badge bg-secondary">نقل</span>',
            'other' => '<span class="badge bg-light">آخر</span>',
        ];
    }

    /**
     * Get plain text label (without HTML)
     */
    public static function text($status, $type = 'custody'): string
    {
        $label = self::label($status, $type);
        // Strip HTML tags
        return strip_tags($label);
    }
}
