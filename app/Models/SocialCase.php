<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasStatusScopes;

class SocialCase extends Model
{
    use SoftDeletes, HasStatusScopes;

    protected $fillable = [
        'researcher_id',
        'name',
        'national_id',
        'phone',
        'description',
        'assistance_type',
        'assistance_other',
        'status',
        'internal_notes',
        'national_id_image',
        'reviewed_at',
        'reviewed_by',
        'is_active',
        // New fields from Excel
        'address',
        'city',
        'district',
        'birth_date',
        'gender',
        'marital_status',
        'family_members_count',
        'monthly_income',
        'monthly_expenses',
        'health_conditions',
        'has_disability',
        'disability_description',
        'special_needs',
        'requested_amount',
        'is_verified',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'birth_date' => 'date',
        'has_disability' => 'boolean',
        'is_verified' => 'boolean',
        'family_members_count' => 'integer',
        'monthly_income' => 'decimal:2',
        'monthly_expenses' => 'decimal:2',
        'requested_amount' => 'decimal:2',
    ];

    public function researcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'researcher_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SocialCaseDocument::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function getTotalSpent()
    {
        return $this->expenses()->sum('amount') ?? 0;
    }
}
