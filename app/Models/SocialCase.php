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
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
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
