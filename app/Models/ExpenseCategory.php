<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'level',
        'name',
        'code',
        'description',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order'     => 'integer',
        'level'     => 'integer',
    ];

    // ──────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order')->orderBy('id');
    }

    /** البنود (المستوى 4) المرتبطة بهذه الفئة */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // ──────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order')->orderBy('id');
    }

    /** فقط الجذور (المستوى الأول) */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /** فقط مستوى معين */
    public function scopeOfLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    /** المسار الكامل: المستوى1 > المستوى2 > ... */
    public function getFullPathAttribute(): string
    {
        $parts = [$this->name];
        $current = $this;
        while ($current->parent_id) {
            $current = $current->parent;
            array_unshift($parts, $current->name);
        }
        return implode(' > ', $parts);
    }
}
