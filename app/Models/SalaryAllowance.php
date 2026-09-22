<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryAllowance extends Model
{
    protected $fillable = [
        'salary_calculation_id',
        'type',
        'name',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function salaryCalculation()
    {
        return $this->belongsTo(SalaryCalculation::class);
    }
}
