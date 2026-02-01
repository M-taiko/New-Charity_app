<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'custody_id',
        'user_id',
        'social_case_id',
        'type',
        'amount',
        'description',
        'location',
        'expense_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'datetime',
    ];

    public function custody(): BelongsTo
    {
        return $this->belongsTo(Custody::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialCase(): BelongsTo
    {
        return $this->belongsTo(SocialCase::class, 'social_case_id');
    }
}
