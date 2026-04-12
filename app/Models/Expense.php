<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'custody_id',
        'treasury_id',
        'user_id',
        'social_case_id',
        'expense_category_id',
        'expense_item_id',
        'type',
        'amount',
        'description',
        'location',
        'expense_date',
        'notes',
        'source',
        'attachment',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'line_items',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'expense_date' => 'datetime',
        'reviewed_at'  => 'datetime',
        'line_items'   => 'array',
        'approval_status' => 'string',
    ];

    public function custody(): BelongsTo
    {
        return $this->belongsTo(Custody::class);
    }

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialCase(): BelongsTo
    {
        return $this->belongsTo(SocialCase::class, 'social_case_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ExpenseItem::class, 'expense_item_id');
    }

    /**
     * Multiple custodies that contributed to this expense
     */
    public function custodies(): BelongsToMany
    {
        return $this->belongsToMany(Custody::class, 'expense_custody')
            ->withPivot('amount')
            ->withTimestamps();
    }

    /**
     * طلبات التعديل على هذا المصروف
     */
    public function editRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExpenseEditRequest::class);
    }

    /**
     * Helper Methods
     */

    /**
     * هل المصروف معتمد؟
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    /**
     * هل هناك طلب تعديل معلق؟
     */
    public function hasPendingEdit(): bool
    {
        try {
            return $this->editRequests()
                ->where('status', 'pending')
                ->exists();
        } catch (\Exception $e) {
            // إذا لم يكن الجدول موجود بعد
            return false;
        }
    }

    /**
     * هل يقدر المستخدم تعديل هذا المصروف؟
     */
    public function canBeEditedBy(\App\Models\User $user): bool
    {
        // إذا كان المصروف معتمداً، فقط المدير يقدر يعدل
        if ($this->isApproved()) {
            return $user->hasRole('مدير');
        }

        // إذا كان هناك طلب تعديل معلق، لا أحد يقدر يعدل
        if ($this->hasPendingEdit()) {
            return false;
        }

        // بعد مراجعة المحاسب: لا يمكن للمندوب التعديل
        if ($this->isReviewed() && $user->hasRole('مندوب')) {
            return false;
        }

        // المندوب صاحب المصروف فقط
        if ($user->hasRole('مندوب')) {
            return $this->user_id === $user->id;
        }

        return $user->hasRole('محاسب') || $user->hasRole('مدير');
    }
}
