@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-users"></i> إدارة الموظفين
                    </h1>
                </div>
                <a href="{{ route('hr.employees.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> إضافة موظف جديد
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="card" data-aos="fade-up">
        <div class="card-header">
            <h5 style="margin: 0;"><i class="fas fa-list"></i> قائمة الموظفين</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>القسم</th>
                            <th>المسمى الوظيفي</th>
                            <th>تاريخ التعيين</th>
                            <th>الراتب</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                        <tr>
                            <td><strong>{{ $employee->user->name }}</strong></td>
                            <td>{{ $employee->department }}</td>
                            <td>{{ $employee->job_title }}</td>
                            <td>{{ $employee->hire_date->format('Y-m-d') }}</td>
                            <td>{{ number_format($employee->salary, 2) }} ج.م</td>
                            <td>
                                <span class="badge bg-{{ $employee->status === 'active' ? 'success' : ($employee->status === 'on_leave' ? 'warning' : 'danger') }}">
                                    {{ match($employee->status) { 'active' => 'نشط', 'inactive' => 'غير نشط', 'on_leave' => 'في إجازة', default => $employee->status } }}
                                </span>
                            </td>
                            <td style="min-width: 100px;">
                                <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deleteEmployee({{ $employee->id }})" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">لا توجد موظفين</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($employees->hasPages())
        <div class="card-footer">{{ $employees->links() }}</div>
        @endif
    </div>
</div>

<script>
    function deleteEmployee(id) {
        if (confirm('هل تريد فعلاً حذف هذا الموظف؟')) {
            // Implement delete via form submission if needed
        }
    }
</script>
@endsection
