<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasStatusScopes;

class Custody extends Model
{
    use SoftDeletes, HasStatusScopes;

    protected $fillable = [
        'treasury_id',
        'agent_id',
        'accountant_id',
        'amount',
        'spent',
        'returned',
        'pending_return',
        'status',
        'notes',
        'accepted_at',
        'returned_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent' => 'decimal:2',
        'returned' => 'decimal:2',
        'pending_return' => 'decimal:2',
        'accepted_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function treasury(): BelongsTo
    {
        return $this->belongsTo(Treasury::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function accountant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accountant_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class);
    }

    public function getRemainingBalance()
    {
        return $this->amount - $this->spent - $this->returned;
    }

    public function getTotalSpent()
    {
        return $this->spent;
    }
}
