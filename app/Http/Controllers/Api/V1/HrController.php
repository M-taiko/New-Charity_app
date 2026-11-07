<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\KpiMetric;
use App\Models\SalaryCalculation;
use App\Models\SalaryAllowance;
use App\Support\Api\ApiList;
use App\Support\Api\ListExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HrController extends Controller
{
    private function money(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('manage_treasury');

        return response()->json([
            'data' => [
                'employees_count' => Employee::count(),
                'active_employees' => Employee::where('status', 'active')->count(),
                'total_salaries' => $this->money(SalaryCalculation::where('status', '!=', 'rejected')->sum('final_salary')),
                'pending_salaries' => SalaryCalculation::where('status', 'draft')->count(),
                'approved_salaries' => SalaryCalculation::where('status', 'approved')->count(),
                'recorded_salaries' => SalaryCalculation::where('status', 'recorded')->count(),
                'today_attendance' => AttendanceRecord::whereDate('date', today())->count(),
                'open_kpis' => KpiMetric::count(),
            ],
        ]);
    }

    public function employees(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            Employee::with('user'),
            $request,
            sortable: $this->employeesSortable(),
            filterable: $this->employeesFilterable(),
            search: $this->employeesSearch(),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
        );
        $paginator->getCollection()->transform(fn ($e) => [
            'id' => $e->id,
            'user_id' => $e->user_id,
            'name' => $e->user?->name,
            'department' => $e->department,
            'job_title' => $e->job_title,
            'hire_date' => $e->hire_date,
            'salary' => $this->money($e->salary),
            'status' => $e->status,
        ]);

        return response()->json(ApiList::envelope($paginator));
    }

    // ── employees declarations — shared by the list and its export (T7-06) ──

    /** @return array<string, string> */
    private function employeesSortable(): array
    {
        return ['name' => 'user_id', 'department' => 'department', 'job_title' => 'job_title', 'salary' => 'salary', 'hire_date' => 'hire_date', 'status' => 'status'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function employeesFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                ['value' => 'active', 'label' => 'نشط'],
                ['value' => 'inactive', 'label' => 'غير نشط'],
            ]],
            'department' => ['type' => 'select', 'column' => 'department', 'options' => fn () => \App\Models\Employee::distinct()->whereNotNull('department')->pluck('department')->map(fn ($d) => ['value' => $d, 'label' => $d])->values()->all()],
        ];
    }

    /** @return callable */
    private function employeesSearch(): callable
    {
        return fn ($q, $term) => $q->where(fn ($w) => $w
            ->where('department', 'like', "%{$term}%")
            ->orWhere('job_title', 'like', "%{$term}%")
            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")));
    }

    /**
     * GET /api/v1/hr/employees/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as employees().
     */
    public function employeesExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            Employee::with('user'),
            $this->employeesSortable(),
            $this->employeesFilterable(),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
            search: $this->employeesSearch(),
            row: fn ($e) => [
                'id' => $e->id,
                'name' => $e->user?->name,
                'department' => $e->department,
                'job_title' => $e->job_title,
                'hire_date' => $e->hire_date?->format('Y-m-d'),
                'salary' => (float) $e->salary,
                'status' => $e->status === 'active' ? 'نشط' : 'غير نشط',
                'phone' => $e->user?->phone,
            ],
        );

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'الاسم'],
            ['key' => 'department', 'label' => 'القسم'],
            ['key' => 'job_title', 'label' => 'المسمى الوظيفي'],
            ['key' => 'hire_date', 'label' => 'تاريخ التعيين'],
            ['key' => 'salary', 'label' => 'الراتب', 'numeric' => true, 'total' => true],
            ['key' => 'status', 'label' => 'الحالة'],
            ['key' => 'phone', 'label' => 'الهاتف'],
        ], 'hr-employees');
    }

    public function attendance(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            AttendanceRecord::with('employee.user'),
            $request,
            sortable: ['date' => 'date', 'employee' => 'employee_id', 'check_in' => 'check_in', 'check_out' => 'check_out', 'status' => 'status'],
            filterable: [
                'employee' => ['type' => 'lookup', 'column' => 'employee_id'],
                'status' => ['type' => 'select', 'column' => 'status'],
            ],
            filters: [
                'employee_id' => fn ($q, $v) => $q->where('employee_id', (int) $v),
                'month' => fn ($q, $v) => $q->whereMonth('date', (int) $v),
                'year' => fn ($q, $v) => $q->whereYear('date', (int) $v),
            ],
        );
        $paginator->getCollection()->transform(fn ($r) => [
            'id' => $r->id,
            'employee' => $r->employee?->user?->name,
            'date' => $r->date?->toIso8601ZuluString(),
            'check_in' => $r->check_in,
            'check_out' => $r->check_out,
            'status' => $r->status,
        ]);

        return response()->json(ApiList::envelope($paginator));
    }

    /**
     * GET /api/v1/hr/attendance/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as attendance().
     */
    public function attendanceExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            AttendanceRecord::with('employee.user'),
            ['date' => 'date', 'employee' => 'employee_id', 'check_in' => 'check_in', 'check_out' => 'check_out', 'status' => 'status'],
            [
                'employee' => ['type' => 'lookup', 'column' => 'employee_id'],
                'status' => ['type' => 'select', 'column' => 'status'],
            ],
            filters: [
                'employee_id' => fn ($q, $v) => $q->where('employee_id', (int) $v),
                'month' => fn ($q, $v) => $q->whereMonth('date', (int) $v),
                'year' => fn ($q, $v) => $q->whereYear('date', (int) $v),
            ],
            row: fn ($r) => [
                'id' => $r->id,
                'employee' => $r->employee?->user?->name,
                'date' => $r->date?->format('Y-m-d'),
                'check_in' => $r->check_in,
                'check_out' => $r->check_out,
                'status' => $this->attendanceStatusLabel((string) $r->status),
            ],
        );

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'employee', 'label' => 'الموظف'],
            ['key' => 'date', 'label' => 'التاريخ'],
            ['key' => 'check_in', 'label' => 'الحضور'],
            ['key' => 'check_out', 'label' => 'الانصراف'],
            ['key' => 'status', 'label' => 'الحالة'],
        ], 'hr-attendance');
    }

    /** Arabic label for an attendance status (export rows) */
    private function attendanceStatusLabel(string $status): string
    {
        return match ($status) {
            'present' => 'حاضر',
            'absent' => 'غائب',
            'late' => 'متأخر',
            'leave' => 'إجازة',
            default => $status,
        };
    }

    public function kpi(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            KpiMetric::with('employee.user'),
            $request,
            sortable: ['date' => 'created_at', 'employee' => 'employee_id', 'name' => 'metric_name', 'target' => 'target_value', 'actual' => 'actual_value', 'percentage' => 'raw:ROUND((actual_value / NULLIF(target_value, 0)) * 100, 1)'],
            filterable: [
                'employee' => ['type' => 'lookup', 'column' => 'employee_id'],
                'name' => ['type' => 'select', 'column' => 'metric_name', 'options' => fn () => \App\Models\KpiMetric::distinct()->pluck('metric_name')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all()],
            ],
            filters: ['employee_id' => fn ($q, $v) => $q->where('employee_id', (int) $v)],
        );
        $paginator->getCollection()->transform(fn ($k) => [
            'id' => $k->id,
            'employee' => $k->employee?->user?->name,
            'name' => $k->metric_name,
            'target' => $this->money($k->target_value),
            'actual' => $this->money($k->actual_value),
            'percentage' => (float) $k->target_value > 0 ? (int) round($k->actual_value / $k->target_value * 100) : 0,
            'period_start' => $k->period_start?->toIso8601ZuluString(),
            'period_end' => $k->period_end?->toIso8601ZuluString(),
        ]);

        return response()->json(ApiList::envelope($paginator));
    }

    /**
     * GET /api/v1/hr/kpi/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as kpi().
     */
    public function kpiExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            KpiMetric::with('employee.user'),
            ['date' => 'created_at', 'employee' => 'employee_id', 'name' => 'metric_name', 'target' => 'target_value', 'actual' => 'actual_value', 'percentage' => 'raw:ROUND((actual_value / NULLIF(target_value, 0)) * 100, 1)'],
            [
                'employee' => ['type' => 'lookup', 'column' => 'employee_id'],
                'name' => ['type' => 'select', 'column' => 'metric_name', 'options' => fn () => \App\Models\KpiMetric::distinct()->pluck('metric_name')->map(fn ($n) => ['value' => $n, 'label' => $n])->values()->all()],
            ],
            filters: ['employee_id' => fn ($q, $v) => $q->where('employee_id', (int) $v)],
            row: fn ($k) => [
                'id' => $k->id,
                'employee' => $k->employee?->user?->name,
                'name' => $k->metric_name,
                'target' => (float) $k->target_value,
                'actual' => (float) $k->actual_value,
                'percentage' => (float) $k->target_value > 0 ? (int) round($k->actual_value / $k->target_value * 100) : 0,
                'period_start' => $k->period_start?->format('Y-m-d'),
                'period_end' => $k->period_end?->format('Y-m-d'),
            ],
        );

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'employee', 'label' => 'الموظف'],
            ['key' => 'name', 'label' => 'المؤشر'],
            ['key' => 'target', 'label' => 'الهدف', 'numeric' => true],
            ['key' => 'actual', 'label' => 'التحقيق الفعلي', 'numeric' => true],
            ['key' => 'percentage', 'label' => 'النسبة %'],
            ['key' => 'period_start', 'label' => 'بداية الفترة'],
            ['key' => 'period_end', 'label' => 'نهاية الفترة'],
        ], 'hr-kpi');
    }

    public function salaries(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            SalaryCalculation::with('employee.user'),
            $request,
            sortable: $this->salariesSortable(),
            filterable: $this->salariesFilterable(),
            filters: $this->salariesFilters(),
        );
        $paginator->getCollection()->transform(fn ($s) => $this->salaryRow($s));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'total_final' => $this->money(SalaryCalculation::sum('final_salary')),
        ]));
    }

    // ── salaries declarations — shared by the list and its exports (T7-06) ──

    /** @return array<string, string> */
    private function salariesSortable(): array
    {
        return ['date' => 'created_at', 'final_salary' => 'final_salary', 'employee' => 'employee_id', 'year' => 'year', 'month' => 'month', 'base_salary' => 'base_salary', 'status' => 'status'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function salariesFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                ['value' => 'calculated', 'label' => 'محسوب'],
                ['value' => 'approved', 'label' => 'معتمد'],
                ['value' => 'paid', 'label' => 'مدفوع'],
            ]],
            'employee' => ['type' => 'lookup', 'column' => 'employee_id'],
        ];
    }

    /** @return array<string, callable> */
    private function salariesFilters(): array
    {
        return [
            'status' => fn ($q, $v) => $q->where('status', $v),
            'month' => fn ($q, $v) => $q->where('month', (int) $v),
            'year' => fn ($q, $v) => $q->where('year', (int) $v),
            'employee_id' => fn ($q, $v) => $q->where('employee_id', (int) $v),
        ];
    }

    /**
     * GET /api/v1/hr/salaries/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as salaries().
     */
    public function salariesExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            SalaryCalculation::with('employee.user'),
            $this->salariesSortable(),
            $this->salariesFilterable(),
            $this->salariesFilters(),
            row: fn ($s) => $this->salaryExportRow($this->salaryRow($s)),
        );

        return ListExport::stream($request, $rows, $this->salaryExportColumns(), 'hr-salaries');
    }

    public function salaryShow(Request $request, SalaryCalculation $salary): JsonResponse
    {
        $salary->load('employee.user');

        return response()->json([
            'data' => $this->salaryRow($salary) + [
                'calculation_method' => $salary->calculation_method,
                'late_days' => (int) $salary->late_days,
                'leave_days' => (int) $salary->leave_days,
                'notes' => $salary->notes,
                'approved_by' => $salary->approver?->name,
                'approved_at' => $salary->approved_at?->toIso8601ZuluString(),
                'paid_at' => $salary->paid_at?->toIso8601ZuluString(),
                'allowances' => SalaryAllowance::where('salary_calculation_id', $salary->id)
                    ->get()->map(fn ($a) => [
                        'id' => $a->id,
                        'type' => $a->type,
                        'description' => $a->description,
                        'amount' => $this->money($a->amount),
                    ]),
            ],
        ]);
    }

    public function salaryHistory(Request $request, Employee $employee): JsonResponse
    {
        $paginator = ApiList::paginate(
            SalaryCalculation::where('employee_id', $employee->id),
            $request,
            sortable: ['date' => 'created_at'],
            // T7-07: declared allowlist — the month/year/status filters the Blade history had
            filterable: $this->salaryHistoryFilterable($employee),
        );
        $paginator->getCollection()->transform(fn ($s) => $this->salaryRow($s));

        return response()->json(ApiList::envelope($paginator));
    }

    /**
     * The salary history allowlist — shared by the list and its export (T7-06).
     *
     * @return array<string, array{type:string, column?:string, options?:callable}>
     */
    private function salaryHistoryFilterable(Employee $employee): array
    {
        return [
            'month' => ['type' => 'select', 'column' => 'month', 'options' => fn () => [
                ['value' => 1, 'label' => 'يناير'],
                ['value' => 2, 'label' => 'فبراير'],
                ['value' => 3, 'label' => 'مارس'],
                ['value' => 4, 'label' => 'أبريل'],
                ['value' => 5, 'label' => 'مايو'],
                ['value' => 6, 'label' => 'يونيو'],
                ['value' => 7, 'label' => 'يوليو'],
                ['value' => 8, 'label' => 'أغسطس'],
                ['value' => 9, 'label' => 'سبتمبر'],
                ['value' => 10, 'label' => 'أكتوبر'],
                ['value' => 11, 'label' => 'نوفمبر'],
                ['value' => 12, 'label' => 'ديسمبر'],
            ]],
            'year' => ['type' => 'select', 'column' => 'year', 'options' => fn () => SalaryCalculation::where('employee_id', $employee->id)->distinct()->orderByDesc('year')->pluck('year')->map(fn ($y) => ['value' => $y, 'label' => (string) $y])->values()->all()],
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                ['value' => 'draft', 'label' => 'مسودة'],
                ['value' => 'calculated', 'label' => 'محسوب'],
                ['value' => 'approved', 'label' => 'معتمد'],
                ['value' => 'paid', 'label' => 'مدفوع'],
            ]],
        ];
    }

    /**
     * GET /api/v1/hr/employees/{employee}/salary-history/export?format=xlsx|pdf|csv
     * (T7-06) — the SAME query/filters as salaryHistory().
     */
    public function salaryHistoryExport(Request $request, Employee $employee): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            SalaryCalculation::where('employee_id', $employee->id)->with('employee.user'),
            ['date' => 'created_at'],
            $this->salaryHistoryFilterable($employee),
            row: fn ($s) => $this->salaryExportRow($this->salaryRow($s)),
        );

        return ListExport::stream($request, $rows, $this->salaryExportColumns(), 'salary-history');
    }

    /** salaryRow mapped to the export shape — floats for amounts, Arabic status */
    private function salaryExportRow(array $s): array
    {
        return [
            'employee' => $s['employee'],
            'year' => $s['year'],
            'month' => $s['month'],
            'base_salary' => $this->moneyFloat($s['base_salary']),
            'calculated_salary' => $this->moneyFloat($s['calculated_salary']),
            'deductions' => $this->moneyFloat($s['deductions']),
            'bonuses' => $this->moneyFloat($s['bonuses']),
            'final_salary' => $this->moneyFloat($s['final_salary']),
            'status' => $this->salaryStatusLabel((string) $s['status']),
        ];
    }

    /** @return array<int, array{key: string, label: string, numeric?: bool, total?: bool}> */
    private function salaryExportColumns(): array
    {
        return [
            ['key' => 'employee', 'label' => 'اسم الموظف'],
            ['key' => 'year', 'label' => 'السنة'],
            ['key' => 'month', 'label' => 'الشهر'],
            ['key' => 'base_salary', 'label' => 'المرتب الأساسي', 'numeric' => true, 'total' => true],
            ['key' => 'calculated_salary', 'label' => 'المرتب المحسوب', 'numeric' => true, 'total' => true],
            ['key' => 'deductions', 'label' => 'الاستقطاعات', 'numeric' => true, 'total' => true],
            ['key' => 'bonuses', 'label' => 'المكافآت', 'numeric' => true, 'total' => true],
            ['key' => 'final_salary', 'label' => 'المرتب النهائي', 'numeric' => true, 'total' => true],
            ['key' => 'status', 'label' => 'الحالة'],
        ];
    }

    /** Arabic label for a salary status (export rows) */
    private function salaryStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'مسودة',
            'calculated' => 'محسوب',
            'approved' => 'معتمد',
            'paid' => 'مدفوع',
            'recorded' => 'مسجل كمصروف',
            default => $status,
        };
    }

    /** export-safe float from a money-formatted row value (commas stripped) */
    private function moneyFloat(mixed $v): float
    {
        return (float) str_replace(',', '', (string) $v);
    }

    private function salaryRow(SalaryCalculation $s): array
    {
        return [
            'id' => $s->id,
            'employee' => $s->employee?->user?->name,
            'employee_id' => $s->employee_id,
            'year' => (int) $s->year,
            'month' => (int) $s->month,
            'base_salary' => $this->money($s->base_salary),
            'attendance_days' => (int) $s->attendance_days,
            'absence_days' => (int) $s->absence_days,
            'calculated_salary' => $this->money($s->calculated_salary),
            'deductions' => $this->money($s->deductions),
            'bonuses' => $this->money($s->bonuses),
            'final_salary' => $this->money($s->final_salary),
            'status' => $s->status,
        ];
    }
}
