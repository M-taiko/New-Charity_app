<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'expense_category_id',
        'name',
        'code',
        'description',
        'default_amount',
        'is_active',
        'order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the expense category that owns this item.
     */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /**
     * Get the expenses for this item.
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Scope to get only active items.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get items ordered by order column.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('id');
    }

    /**
     * Scope to get items for a specific category.
     */
    public function scopeForCategory($query, $categoryId)
    {
        return $query->where('expense_category_id', $categoryId);
    }
}
