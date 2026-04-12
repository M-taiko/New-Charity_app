<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\AttendanceRecord;
use App\Models\KpiMetric;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HrController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function checkHrAccess()
    {
        if (!auth()->user()->hasRole('مدير') && !auth()->user()->hasRole('محاسب')) {
            abort(403, 'غير مصرح لك بالوصول إلى هذه الصفحة');
        }
    }

    /**
     * Show HR dashboard
     */
    public function dashboard()
    {
        $this->checkHrAccess();
        $employeeCount = Employee::where('status', 'active')->count();
        $totalSalary = Employee::where('status', 'active')->sum('salary');
        $attendanceToday = AttendanceRecord::whereDate('date', today())->count();

        return view('hr.dashboard', compact('employeeCount', 'totalSalary', 'attendanceToday'));
    }

    /**
     * List all employees
     */
    public function index(Request $request)
    {
        $this->checkHrAccess();
        $query = Employee::with('user');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $employees = $query->paginate(20);
        $departments = Employee::distinct()->pluck('department');

        return view('hr.employees.index', compact('employees', 'departments'));
    }

    /**
     * Show employee form
     */
    public function create()
    {
        $this->checkHrAccess();
        try {
            $users = User::whereDoesntHave('employee')->get();
            return view('hr.employees.form', compact('users'));
        } catch (\Exception $e) {
            // If there's an error with the relation, just get all users
            $users = User::all();
            return view('hr.employees.form', compact('users'));
        }
    }

    /**
     * Store new employee
     */
    public function store(Request $request)
    {
        $this->checkHrAccess();
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id|unique:employees',
                'department' => 'required|string',
                'job_title' => 'required|string',
                'hire_date' => 'required|date',
                'salary' => 'required|numeric|min:0',
            ]);

            Employee::create($request->only(['user_id', 'department', 'job_title', 'hire_date', 'salary']));

            return redirect()->route('hr.employees.index')->with('success', 'تم إضافة الموظف بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Edit employee
     */
    public function edit(Employee $employee)
    {
        $this->checkHrAccess();
        return view('hr.employees.form', compact('employee'));
    }

    /**
     * Update employee
     */
    public function update(Request $request, Employee $employee)
    {
        $this->checkHrAccess();
        try {
            $request->validate([
                'department' => 'required|string',
                'job_title' => 'required|string',
                'hire_date' => 'required|date',
                'salary' => 'required|numeric|min:0',
                'status' => 'required|in:active,inactive,on_leave',
            ]);

            $employee->update($request->only(['department', 'job_title', 'hire_date', 'salary', 'status']));

            return redirect()->route('hr.employees.index')->with('success', 'تم تحديث بيانات الموظف');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Show attendance page
     */
    public function attendanceIndex(Request $request)
    {
        $this->checkHrAccess();
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $employees = Employee::with(['attendanceRecords' => function ($query) use ($month, $year) {
            $query->whereMonth('date', $month)->whereYear('date', $year);
        }])->where('status', 'active')->get();

        return view('hr.attendance.index', compact('employees', 'month', 'year'));
    }

    /**
     * Record attendance
     */
    public function attendanceStore(Request $request)
    {
        $this->checkHrAccess();
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'date' => 'required|date',
                'check_in' => 'nullable|date_format:H:i',
                'check_out' => 'nullable|date_format:H:i',
                'status' => 'required|in:present,absent,late,leave',
            ]);

            AttendanceRecord::updateOrCreate(
                ['employee_id' => $request->employee_id, 'date' => $request->date],
                [
                    'check_in' => $request->check_in,
                    'check_out' => $request->check_out,
                    'status' => $request->status,
                ]
            );

            return back()->with('success', 'تم تسجيل الحضور بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Show KPI dashboard
     */
    public function kpiIndex(Request $request)
    {
        $this->checkHrAccess();
        $employees = Employee::with(['kpiMetrics' => function ($query) {
            $query->orderByDesc('period_end');
        }])->where('status', 'active')->get();

        return view('hr.kpi.index', compact('employees'));
    }

    /**
     * Store/Update KPI metrics
     */
    public function kpiStore(Request $request)
    {
        $this->checkHrAccess();
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'metric_name' => 'required|string',
                'target_value' => 'required|numeric|min:0',
                'actual_value' => 'required|numeric|min:0',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after:period_start',
            ]);

            $kpi = KpiMetric::create([
                'employee_id' => $request->employee_id,
                'metric_name' => $request->metric_name,
                'target_value' => $request->target_value,
                'actual_value' => $request->actual_value,
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'score' => null,
            ]);

            // Calculate score
            $kpi->score = $kpi->calculateScore();
            $kpi->save();

            return back()->with('success', 'تم إضافة مؤشر KPI بنجاح');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'حدث خطأ: ' . $e->getMessage());
        }
    }
}
