<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustodyTransfer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'from_agent_id',
        'to_agent_id',
        'custody_id',
        'amount',
        'status',
        'notes',
        'rejection_reason',
        'approved_at',
        'approved_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the agent who initiated the transfer.
     */
    public function fromAgent()
    {
        return $this->belongsTo(User::class, 'from_agent_id');
    }

    /**
     * Get the agent who receives the transfer.
     */
    public function toAgent()
    {
        return $this->belongsTo(User::class, 'to_agent_id');
    }

    /**
     * Get the user who approved the transfer.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the custody being transferred.
     */
    public function custody()
    {
        return $this->belongsTo(Custody::class);
    }

    /**
     * Scope to get pending transfers.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved transfers.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected transfers.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope to get transfers sent by a specific user.
     */
    public function scopeSentBy($query, $userId)
    {
        return $query->where('from_agent_id', $userId);
    }

    /**
     * Scope to get transfers received by a specific user.
     */
    public function scopeReceivedBy($query, $userId)
    {
        return $query->where('to_agent_id', $userId);
    }

    /**
     * Check if transfer is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transfer is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if transfer is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
