@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-calendar-check"></i> تسجيل الحضور والغياب
            </h1>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('hr.attendance.index') }}" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">الشهر</label>
                            <input type="number" name="month" class="form-control" min="1" max="12" value="{{ $month }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">السنة</label>
                            <input type="number" name="year" class="form-control" value="{{ $year }}">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> تصفية
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card" data-aos="fade-up">
        <div class="card-header">
            <h5 style="margin: 0;"><i class="fas fa-list"></i> سجل الحضور - {{ $month }}/{{ $year }}</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>الموظف</th>
                            <th>التاريخ</th>
                            <th>الحضور</th>
                            <th>الانصراف</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                            @if($employee->attendanceRecords->isNotEmpty())
                                @foreach($employee->attendanceRecords as $record)
                                <tr>
                                    <td><strong>{{ $employee->user->name }}</strong></td>
                                    <td>{{ $record->date->format('Y-m-d') }}</td>
                                    <td>{{ $record->check_in ?? '-' }}</td>
                                    <td>{{ $record->check_out ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $record->status === 'present' ? 'success' : ($record->status === 'absent' ? 'danger' : ($record->status === 'late' ? 'warning' : 'info')) }}">
                                            {{ match($record->status) { 'present' => 'حاضر', 'absent' => 'غائب', 'late' => 'متأخر', 'leave' => 'إجازة', default => $record->status } }}
                                        </span>
                                    </td>
                                    <td>
                                        <button onclick="editAttendance({{ $record->id }})" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-2">
                                        {{ $employee->user->name }} - لا توجد سجلات
                                    </td>
                                </tr>
                            @endif
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">لا توجد موظفين نشطين</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4" data-aos="fade-up">
        <div class="card-header">
            <h5 style="margin: 0;"><i class="fas fa-plus-circle"></i> إضافة سجل حضور جديد</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('hr.attendance.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><strong>الموظف <span class="text-danger">*</span></strong></label>
                        <select name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                            <option value="">-- اختر موظف --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->user->name }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><strong>التاريخ <span class="text-danger">*</span></strong></label>
                        <input type="date" name="date" class="form-control @error('date') is-invalid @enderror" required>
                        @error('date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">الحضور</label>
                        <input type="time" name="check_in" class="form-control">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">الانصراف</label>
                        <input type="time" name="check_out" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><strong>الحالة <span class="text-danger">*</span></strong></label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="">-- اختر --</option>
                            <option value="present">حاضر</option>
                            <option value="absent">غائب</option>
                            <option value="late">متأخر</option>
                            <option value="leave">إجازة</option>
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-8 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-save"></i> إضافة السجل
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editAttendance(id) {
        // Implement edit modal if needed
    }
</script>
@endsection
