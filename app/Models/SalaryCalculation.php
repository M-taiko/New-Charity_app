<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryCalculation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'base_salary',
        'total_working_days',
        'attendance_days',
        'absence_days',
        'late_days',
        'leave_days',
        'calculated_salary',
        'deductions',
        'bonuses',
        'final_salary',
        'calculation_method',
        'status',
        'calculated_by',
        'approved_by',
        'calculated_at',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'base_salary' => 'decimal:2',
        'calculated_salary' => 'decimal:2',
        'deductions' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'final_salary' => 'decimal:2',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function calculator()
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function allowances()
    {
        return $this->hasMany(SalaryAllowance::class);
    }

    public function deductions()
    {
        return $this->allowances()->where('type', 'deduction');
    }

    public function bonuses()
    {
        return $this->allowances()->where('type', 'bonus');
    }

    // Scopes
    public function scopeForMonth($query, $year, $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved')->orWhere('status', 'paid');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // Methods
    public function calculateSalary()
    {
        $dailyRate = $this->base_salary / $this->total_working_days;

        if ($this->calculation_method === 'daily_rate') {
            $this->calculated_salary = $dailyRate * $this->attendance_days;
        } else {
            // Deduction method: full salary minus deductions for absences
            $this->calculated_salary = $this->base_salary - ($dailyRate * $this->absence_days);
        }

        $this->final_salary = $this->calculated_salary + $this->bonuses - $this->deductions;

        return $this;
    }

    public function approve(User $user)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
    }

    public function markAsPaid()
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function getTotalDeductions()
    {
        return $this->allowances()->where('type', 'deduction')->sum('amount');
    }

    public function getTotalBonuses()
    {
        return $this->allowances()->where('type', 'bonus')->sum('amount');
    }
}
