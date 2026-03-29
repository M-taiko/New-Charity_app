<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'created_by',
        'assigned_to',
        'status',
        'priority',
        'due_date',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at', 'asc');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pending'     => 'قيد الانتظار',
            'in_progress' => 'جاري التنفيذ',
            'completed'   => 'مكتملة',
            'cancelled'   => 'ملغاة',
            default       => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'pending'     => 'warning',
            'in_progress' => 'primary',
            'completed'   => 'success',
            'cancelled'   => 'secondary',
            default       => 'secondary',
        };
    }

    public function priorityLabel(): string
    {
        return match($this->priority) {
            'low'    => 'منخفضة',
            'medium' => 'متوسطة',
            'high'   => 'عالية',
            default  => $this->priority,
        };
    }

    public function priorityColor(): string
    {
        return match($this->priority) {
            'low'    => 'success',
            'medium' => 'warning',
            'high'   => 'danger',
            default  => 'secondary',
        };
    }
}
