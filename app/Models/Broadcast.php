<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    protected $fillable = ['created_by', 'title', 'message', 'level', 'is_active', 'expires_at', 'target_type', 'target_user_id'];

    protected $casts = [
        'is_active'  => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Active broadcasts that haven't expired and are targeted to current user
     */
    public static function activeNow(int $userId = 0): ?self
    {
        return static::where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q) => $q->where('target_type', 'all')
                                ->orWhere(fn($q2) => $q2->where('target_type', 'user')
                                                       ->where('target_user_id', $userId)))
            ->latest()
            ->first();
    }
}
