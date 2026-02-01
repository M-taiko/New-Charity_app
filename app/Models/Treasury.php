<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treasury extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'balance',
        'notes',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class);
    }

    public function custodies(): HasMany
    {
        return $this->hasMany(Custody::class);
    }
}
