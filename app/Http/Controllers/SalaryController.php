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

    public function __construct(private \App\Services\ExportService $exports, SalaryCalculationService $salaryService)
    {
        $this->salaryService = $salaryService;
        $this->middleware('auth');
    }

    private function checkHrAccess()
    {
        if (!auth()->user()->hasRole('مدير') && !auth()->user()->hasRole('محاسب')) {
            // للطلبات الأجاكس (DataTables): JSON بدل صفحة HTML حتى لا يظهر تحذير "Invalid JSON response"
            if (request()->expectsJson() || request()->ajax()) {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json(['error' => 'غير مصرح لك بالوصول إلى بيانات الرواتب'], 403)
                );
            }
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

        // T6-71: the shared export service — UTF-8 BOM, xlsx/pdf/csv, totals,
        // a real response (no header()+exit), audit-logged
        $report = $this->salaryService->getMonthlyReport($request->year, $request->month);
        $rows = collect($report['salaries'])->map(fn ($salary) => [
            'employee' => $salary->employee->user->name,
            'base_salary' => (float) $salary->base_salary,
            'attendance_days' => (int) $salary->attendance_days,
            'absence_days' => (int) $salary->absence_days,
            'calculated_salary' => (float) $salary->calculated_salary,
            'deductions' => (float) $salary->deductions,
            'bonuses' => (float) $salary->bonuses,
            'final_salary' => (float) $salary->final_salary,
        ]);

        return $this->exports->export($request, $rows, [
            ['key' => 'employee', 'label' => 'اسم الموظف'],
            ['key' => 'base_salary', 'label' => 'المرتب الأساسي', 'numeric' => true, 'total' => true],
            ['key' => 'attendance_days', 'label' => 'أيام الحضور', 'numeric' => true],
            ['key' => 'absence_days', 'label' => 'أيام الغياب', 'numeric' => true],
            ['key' => 'calculated_salary', 'label' => 'المرتب المحسوب', 'numeric' => true],
            ['key' => 'deductions', 'label' => 'الخصومات', 'numeric' => true, 'total' => true],
            ['key' => 'bonuses', 'label' => 'العلاوات', 'numeric' => true, 'total' => true],
            ['key' => 'final_salary', 'label' => 'المرتب النهائي', 'numeric' => true, 'total' => true],
        ], 'salary-report');
    }

    /**
     * GET /api/v1/hr/salaries/{salary}/payslip (T6-71.6) — a one-page Arabic PDF payslip.
     */
    public function payslip(int $salaryId)
    {
        $this->checkHrAccess();

        $salary = \App\Models\SalaryCalculation::with(['employee.user'])->findOrFail($salaryId);
        $employee = $salary->employee;
        $org = (string) \App\Models\Setting::get('org_name', 'Naguib Selim Charity');

        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'directionality' => 'rtl', 'default_font' => 'dejavusans']);
        $row = fn (string $label, string $value) => "<tr><td style=\"border:0.5px solid #999;padding:6px;width:45%;background:#F7F3EF;\">{$label}</td><td style=\"border:0.5px solid #999;padding:6px;direction:ltr;text-align:right;\">{$value}</td></tr>";
        $html = '<html dir="rtl"><body style="font-family: dejavusans;">'
            . '<h2 style="text-align:center;margin:0;">' . e($org) . '</h2>'
            . '<p style="text-align:center;margin:0 0 10px;">قسيمة مرتب — ' . $salary->year . '/' . str_pad((string) $salary->month, 2, '0', STR_PAD_LEFT) . '</p>'
            . '<table style="border-collapse:collapse;width:100%;font-size:12px;">'
            . $row('الموظف', e($employee->user->name ?? '-'))
            . $row('الوظيفة', e($employee->job_title ?? '-'))
            . $row('المرتب الأساسي', number_format((float) $salary->base_salary, 2) . ' ج.م')
            . $row('أيام الحضور', (string) $salary->attendance_days)
            . $row('أيام الغياب', (string) $salary->absence_days)
            . $row('المرتب المحسوب', number_format((float) $salary->calculated_salary, 2) . ' ج.م')
            . $row('الخصومات', number_format((float) $salary->deductions, 2) . ' ج.م')
            . $row('العلاوات', number_format((float) $salary->bonuses, 2) . ' ج.م')
            . '<tr><th colspan="2" style="border:0.5px solid #999;padding:8px;background:#DFD7D3;text-align:center;font-size:14px;">الصافي: ' . number_format((float) $salary->final_salary, 2) . ' ج.م</th></tr>'
            . '</table></body></html>';
        $mpdf->WriteHTML($html);

        return response()->streamDownload(
            fn () => $mpdf->Output('payslip-' . ($employee->user->name ?? 'employee') . '-' . $salary->year . '-' . $salary->month . '.pdf', \Mpdf\Output\Destination::DOWNLOAD),
            'payslip.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Get salary calculations data for DataTables
     */
    public function tableData()
    {
        $this->checkHrAccess();

        $salaries = SalaryCalculation::with(['employee', 'employee.user'])->latest()->get();

        return \Yajra\DataTables\DataTables::of($salaries)
            ->addColumn('employee_name', function ($row) {
                return $row->employee?->user?->name ?? '-';
            })
            ->addColumn('period_label', function ($row) {
                return Carbon::createFromDate($row->year, $row->month, 1)->format('F Y');
            })
            ->addColumn('status_label', function ($row) {
                return match($row->status ?? 'draft') {
                    'approved' => 'معتمدة',
                    'paid' => 'مرحلة',
                    default => 'مسودة',
                };
            })
            ->addColumn('allowances_total', function ($row) {
                return $row->bonuses ?? 0;
            })
            ->addColumn('deductions_total', function ($row) {
                return $row->deductions ?? 0;
            })
            ->addColumn('total_salary', function ($row) {
                return $row->final_salary ?? 0;
            })
            ->make(true);
    }
}
