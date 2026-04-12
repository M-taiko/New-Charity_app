<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryCalculation;
use App\Services\SalaryCalculationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SalaryController extends Controller
{
    private $salaryService;

    public function __construct(SalaryCalculationService $salaryService)
    {
        $this->salaryService = $salaryService;
        $this->middleware('auth');
    }

    private function checkHrAccess()
    {
        if (!auth()->user()->hasRole('مدير') && !auth()->user()->hasRole('محاسب')) {
            abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة');
        }
    }

    /**
     * Show salary calculation dashboard
     */
    public function index(Request $request)
    {
        $this->checkHrAccess();

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $salaries = SalaryCalculation::forMonth($year, $month)
            ->with(['employee', 'employee.user'])
            ->paginate(20);

        $report = $this->salaryService->getMonthlyReport($year, $month);
        $years = range(now()->year - 2, now()->year + 1);
        $months = range(1, 12);

        return view('hr.salaries.index', compact('salaries', 'report', 'year', 'month', 'years', 'months'));
    }

    /**
     * Calculate salaries for a specific month
     */
    public function calculate(Request $request)
    {
        $this->checkHrAccess();

        $request->validate([
            'year' => 'required|integer|min:2024|max:' . (now()->year + 1),
            'month' => 'required|integer|min:1|max:12',
            'calculation_method' => 'required|in:daily_rate,deduction_method',
        ]);

        try {
            $salaries = $this->salaryService->calculateMonthSalaries(
                $request->year,
                $request->month,
                $request->calculation_method
            );

            return response()->json([
                'success' => true,
                'message' => "تم حساب مرتبات {$request->month}/{$request->year} بنجاح",
                'data' => $salaries,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Show salary details
     */
    public function show(SalaryCalculation $salary)
    {
        $this->checkHrAccess();
        $this->authorize('manage_expense_items');

        $salary->load(['employee', 'employee.user', 'allowances']);

        return view('hr.salaries.show', compact('salary'));
    }

    /**
     * Edit salary calculation
     */
    public function edit(SalaryCalculation $salary)
    {
        $this->checkHrAccess();

        if ($salary->status === 'paid') {
            return back()->with('error', 'لا يمكن تعديل راتب تم دفعه');
        }

        $salary->load(['employee', 'employee.user', 'allowances']);

        return view('hr.salaries.edit', compact('salary'));
    }

    /**
     * Update salary calculation
     */
    public function update(Request $request, SalaryCalculation $salary)
    {
        $this->checkHrAccess();

        if ($salary->status === 'paid') {
            return back()->with('error', 'لا يمكن تعديل راتب تم دفعه');
        }

        $request->validate([
            'base_salary' => 'required|numeric|min:0',
            'attendance_days' => 'required|integer|min:0|max:31',
            'absence_days' => 'required|integer|min:0|max:31',
            'late_days' => 'required|integer|min:0|max:31',
            'leave_days' => 'required|integer|min:0|max:31',
            'calculation_method' => 'required|in:daily_rate,deduction_method',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $salary->update([
                'base_salary' => $request->base_salary,
                'attendance_days' => $request->attendance_days,
                'absence_days' => $request->absence_days,
                'late_days' => $request->late_days,
                'leave_days' => $request->leave_days,
                'calculation_method' => $request->calculation_method,
                'notes' => $request->notes,
            ]);

            $salary->calculateSalary();
            $salary->save();

            return redirect()->route('salaries.show', $salary)->with('success', 'تم تحديث حساب المرتب بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Add allowance (deduction or bonus)
     */
    public function addAllowance(Request $request, SalaryCalculation $salary)
    {
        $this->checkHrAccess();

        $request->validate([
            'type' => 'required|in:deduction,bonus',
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $this->salaryService->addAllowance(
                $salary,
                $request->type,
                $request->name,
                $request->amount,
                $request->description
            );

            return back()->with('success', 'تم إضافة ' . ($request->type === 'deduction' ? 'الخصم' : 'العلاوة') . ' بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Approve salary
     */
    public function approve(Request $request, SalaryCalculation $salary)
    {
        $this->checkHrAccess();
        $this->authorize('manage_expense_items');

        try {
            $this->salaryService->approveSalary($salary);

            return back()->with('success', 'تمت الموافقة على المرتب بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Record salary as expense
     */
    public function recordExpense(Request $request, SalaryCalculation $salary)
    {
        $this->checkHrAccess();
        $this->authorize('spend_money');

        try {
            if ($salary->status !== 'approved') {
                return back()->with('error', 'يجب الموافقة على المرتب أولاً');
            }

            $this->salaryService->recordSalaryAsExpense($salary);

            return back()->with('success', 'تم تسجيل المرتب كمصروف بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Get employee salary history
     */
    public function employeeHistory(Employee $employee)
    {
        $this->checkHrAccess();

        $history = $this->salaryService->getEmployeeSalaryHistory($employee);

        return view('hr.salaries.employee-history', compact('employee', 'history'));
    }

    /**
     * Export monthly report
     */
    public function exportReport(Request $request)
    {
        $this->checkHrAccess();

        $request->validate([
            'year' => 'required|integer',
            'month' => 'required|integer',
        ]);

        $report = $this->salaryService->getMonthlyReport($request->year, $request->month);

        // Generate CSV or Excel
        $fileName = "salary_report_{$request->year}_{$request->month}.csv";
        $handle = fopen('php://output', 'w');

        header('Content-Type: text/csv;charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$fileName\"");

        fputcsv($handle, ['اسم الموظف', 'المرتب الأساسي', 'أيام الحضور', 'أيام الغياب', 'المرتب المحسوب', 'الخصومات', 'العلاوات', 'المرتب النهائي']);

        foreach ($report['salaries'] as $salary) {
            fputcsv($handle, [
                $salary->employee->user->name,
                $salary->base_salary,
                $salary->attendance_days,
                $salary->absence_days,
                $salary->calculated_salary,
                $salary->deductions,
                $salary->bonuses,
                $salary->final_salary,
            ]);
        }

        fputcsv($handle, ['', '', '', '', '', '', 'الإجمالي', $report['total_salaries']]);

        fclose($handle);
        exit;
    }
}
