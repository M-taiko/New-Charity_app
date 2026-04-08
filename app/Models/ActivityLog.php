<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Arabic label for the event type
     */
    public function getEventLabelAttribute(): string
    {
        return match($this->event) {
            'created'   => 'إنشاء',
            'updated'   => 'تعديل',
            'deleted'   => 'حذف',
            'approved'  => 'موافقة',
            'rejected'  => 'رفض',
            'returned'  => 'رد',
            'reviewed'  => 'مراجعة',
            'login'     => 'تسجيل دخول',
            'logout'    => 'تسجيل خروج',
            'sent'      => 'إرسال',
            'closed'    => 'إقفال',
            'assigned'  => 'تكليف',
            'completed' => 'إتمام',
            default     => $this->event,
        };
    }

    /**
     * CSS badge color for the event
     */
    public function getEventColorAttribute(): string
    {
        return match($this->event) {
            'created'   => 'success',
            'updated'   => 'info',
            'deleted'   => 'danger',
            'approved'  => 'success',
            'rejected'  => 'danger',
            'returned'  => 'warning',
            'reviewed'  => 'primary',
            'login'     => 'secondary',
            'logout'    => 'secondary',
            'closed'    => 'dark',
            'assigned'  => 'primary',
            'completed' => 'success',
            default     => 'secondary',
        };
    }

    /**
     * Short model name for display
     */
    public function getSubjectLabelAttribute(): string
    {
        return match($this->subject_type) {
            'App\Models\Custody'        => 'عهدة',
            'App\Models\Expense'        => 'مصروف',
            'App\Models\SocialCase'     => 'حالة اجتماعية',
            'App\Models\Treasury'       => 'خزينة',
            'App\Models\User'           => 'مستخدم',
            'App\Models\Task'           => 'مهمة',
            'App\Models\CustodyTransfer'=> 'تحويل عهدة',
            default                     => class_basename($this->subject_type),
        };
    }
}
