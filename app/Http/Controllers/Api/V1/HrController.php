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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            sortable: ['name' => 'user_id', 'department' => 'department', 'job_title' => 'job_title', 'salary' => 'salary', 'hire_date' => 'hire_date', 'status' => 'status'],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('department', 'like', "%{$term}%")
                ->orWhere('job_title', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))),
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

    public function attendance(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            AttendanceRecord::with('employee.user'),
            $request,
            sortable: ['date' => 'date', 'employee' => 'employee_id', 'check_in' => 'check_in', 'check_out' => 'check_out', 'status' => 'status'],
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

    public function kpi(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            KpiMetric::with('employee.user'),
            $request,
            sortable: ['date' => 'created_at', 'employee' => 'employee_id', 'name' => 'metric_name', 'target' => 'target_value', 'actual' => 'actual_value', 'percentage' => 'raw:ROUND((actual_value / NULLIF(target_value, 0)) * 100, 1)'],
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

    public function salaries(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            SalaryCalculation::with('employee.user'),
            $request,
            sortable: ['date' => 'created_at', 'final_salary' => 'final_salary', 'employee' => 'employee_id', 'year' => 'year', 'month' => 'month', 'base_salary' => 'base_salary', 'status' => 'status'],
            filters: [
                'status' => fn ($q, $v) => $q->where('status', $v),
                'month' => fn ($q, $v) => $q->where('month', (int) $v),
                'year' => fn ($q, $v) => $q->where('year', (int) $v),
                'employee_id' => fn ($q, $v) => $q->where('employee_id', (int) $v),
            ],
        );
        $paginator->getCollection()->transform(fn ($s) => $this->salaryRow($s));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'total_final' => $this->money(SalaryCalculation::sum('final_salary')),
        ]));
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
            $request, sortable: ['date' => 'created_at'],
        );
        $paginator->getCollection()->transform(fn ($s) => $this->salaryRow($s));

        return response()->json(ApiList::envelope($paginator));
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
