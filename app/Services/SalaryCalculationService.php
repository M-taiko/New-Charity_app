<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SalaryCalculation;
use App\Models\AttendanceRecord;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalaryCalculationService
{
    /**
     * Calculate salary for an employee in a specific month
     */
    public function calculateEmployeeSalary(Employee $employee, $year, $month, $calculationMethod = 'daily_rate')
    {
        DB::transaction(function() use ($employee, $year, $month, $calculationMethod) {
            // Get or create salary calculation record
            $salary = SalaryCalculation::firstOrCreate(
                [
                    'employee_id' => $employee->id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'base_salary' => $employee->salary,
                    'calculation_method' => $calculationMethod,
                    'calculated_by' => auth()->id(),
                    'calculated_at' => now(),
                    'status' => 'calculated',
                ]
            );

            // Count attendance records for the month
            $attendanceRecords = AttendanceRecord::where('employee_id', $employee->id)
                ->whereYear('date', $year)
                ->whereMonth('date', $month)
                ->get();

            $attendanceDays = $attendanceRecords->where('status', 'present')->count();
            $absenceDays = $attendanceRecords->where('status', 'absent')->count();
            $lateDays = $attendanceRecords->where('status', 'late')->count();
            $leaveDays = $attendanceRecords->where('status', 'leave')->count();

            // Update attendance data
            $salary->update([
                'attendance_days' => $attendanceDays,
                'absence_days' => $absenceDays,
                'late_days' => $lateDays,
                'leave_days' => $leaveDays,
            ]);

            // Calculate salary
            $salary->calculateSalary();
            $salary->save();

            return $salary;
        });

        return SalaryCalculation::where('employee_id', $employee->id)
            ->forMonth($year, $month)
            ->first();
    }

    /**
     * Calculate salary for all active employees
     */
    public function calculateMonthSalaries($year, $month, $calculationMethod = 'daily_rate')
    {
        $employees = Employee::where('status', 'active')->get();
        $calculations = [];

        foreach ($employees as $employee) {
            $calculations[] = $this->calculateEmployeeSalary($employee, $year, $month, $calculationMethod);
        }

        return $calculations;
    }

    /**
     * Add deduction or bonus to salary
     */
    public function addAllowance(SalaryCalculation $salary, $type, $name, $amount, $description = null)
    {
        $salary->allowances()->create([
            'type' => $type,
            'name' => $name,
            'amount' => $amount,
            'description' => $description,
        ]);

        // Recalculate salary
        $salary->update([
            'deductions' => $salary->deductions()->sum('amount'),
            'bonuses' => $salary->bonuses()->sum('amount'),
        ]);

        $salary->calculateSalary();
        $salary->save();

        return $salary;
    }

    /**
     * Register salary payment as expense
     */
    public function recordSalaryAsExpense(SalaryCalculation $salary)
    {
        if ($salary->status === 'paid') {
            return null; // Already recorded
        }

        DB::transaction(function() use ($salary) {
            // Create expense for salary
            $expense = Expense::create([
                'amount' => $salary->final_salary,
                'type' => 'salary',
                'description' => "راتب {$salary->employee->user->name} - {$salary->month}/{$salary->year}",
                'category' => 'رواتب الموظفين',
                'treasury_id' => null, // Or get default treasury
                'expense_date' => now()->endOfMonth(),
                'notes' => "حساب المرتب: أيام حضور {$salary->attendance_days} - أيام غياب {$salary->absence_days}",
            ]);

            // Mark salary as paid
            $salary->markAsPaid();

            return $expense;
        });
    }

    /**
     * Approve salary calculation
     */
    public function approveSalary(SalaryCalculation $salary)
    {
        $salary->approve(auth()->user());
    }

    /**
     * Get salary report for a month
     */
    public function getMonthlyReport($year, $month)
    {
        $salaries = SalaryCalculation::forMonth($year, $month)
            ->with(['employee', 'employee.user'])
            ->get();

        return [
            'year' => $year,
            'month' => $month,
            'total_salaries' => $salaries->sum('final_salary'),
            'count' => $salaries->count(),
            'salaries' => $salaries,
        ];
    }

    /**
     * Get salary history for an employee
     */
    public function getEmployeeSalaryHistory(Employee $employee, $limit = 12)
    {
        return SalaryCalculation::forEmployee($employee->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit($limit)
            ->get();
    }
}
