<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseEditRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_id',
        'requested_by',
        'reviewed_by',
        'original_data',
        'requested_changes',
        'status',
        'rejection_reason',
        'requested_at',
        'reviewed_at',
    ];

    protected $casts = [
        'original_data' => 'array',
        'requested_changes' => 'array',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * العلاقات
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Helper Methods
     */

    /**
     * هل الطلب معلق؟
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * هل تمت الموافقة على الطلب؟
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * هل تم رفض الطلب؟
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * الحصول على البيانات التي تغيرت
     */
    public function getChangedFields(): array
    {
        $changed = [];
        foreach ($this->requested_changes as $field => $newValue) {
            $oldValue = $this->original_data[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changed[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        return $changed;
    }

    /**
     * ترجمة أسماء الحقول
     */
    public static function fieldLabels(): array
    {
        return [
            'amount' => 'المبلغ',
            'description' => 'الوصف',
            'location' => 'الموقع',
            'expense_category_id' => 'فئة المصروف',
            'expense_item_id' => 'بند المصروف',
            'social_case_id' => 'الحالة الاجتماعية',
            'attachment' => 'المرفق',
        ];
    }

    public static function fieldLabel(string $field): string
    {
        return self::fieldLabels()[$field] ?? $field;
    }
}
