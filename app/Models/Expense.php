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
        'expense_category_id',
        'expense_item_id',
        'type',
        'amount',
        'description',
        'location',
        'expense_date',
        'notes',
        'source',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ExpenseItem::class, 'expense_item_id');
    }
}
