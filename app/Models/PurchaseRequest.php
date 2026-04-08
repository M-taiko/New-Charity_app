<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'requested_by', 'reviewed_by', 'supplier_id',
        'title', 'description', 'estimated_cost', 'actual_cost',
        'priority', 'status', 'category',
        'attachment', 'rejection_reason', 'needed_by', 'reviewed_at',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost'    => 'decimal:2',
        'needed_by'      => 'date',
        'reviewed_at'    => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'في الانتظار',
            'approved'  => 'موافق عليه',
            'rejected'  => 'مرفوض',
            'purchased' => 'تم الشراء',
            'cancelled' => 'ملغي',
            default     => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending'   => 'warning',
            'approved'  => 'success',
            'rejected'  => 'danger',
            'purchased' => 'primary',
            'cancelled' => 'secondary',
            default     => 'secondary',
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match($this->priority) {
            'low'    => 'منخفضة',
            'medium' => 'متوسطة',
            'high'   => 'عالية',
            'urgent' => 'عاجل',
            default  => $this->priority,
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low'    => 'secondary',
            'medium' => 'info',
            'high'   => 'warning',
            'urgent' => 'danger',
            default  => 'secondary',
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'office_supplies' => 'مستلزمات مكتبية',
            'equipment'       => 'معدات وأجهزة',
            'services'        => 'خدمات',
            'maintenance'     => 'صيانة',
            'other'           => 'أخرى',
            default           => $this->category,
        };
    }
}
