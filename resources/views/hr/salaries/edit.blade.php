@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-edit"></i> تعديل حساب المرتب
            </h1>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8 offset-lg-2">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-calculator"></i> {{ $salary->employee->user->name }} - {{ $salary->month }}/{{ $salary->year }}
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('hr.salaries.update', $salary) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Basic Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                البيانات الأساسية
                            </h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>المرتب الأساسي (ج.م) <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="base_salary" class="form-control @error('base_salary') is-invalid @enderror"
                                           value="{{ old('base_salary', $salary->base_salary) }}" step="0.01" min="0" required>
                                    @error('base_salary')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>طريقة الحساب <span class="text-danger">*</span></strong></label>
                                    <select name="calculation_method" class="form-select @error('calculation_method') is-invalid @enderror" required>
                                        <option value="daily_rate" {{ old('calculation_method', $salary->calculation_method) === 'daily_rate' ? 'selected' : '' }}>
                                            المرتب / أيام العمل × أيام الحضور
                                        </option>
                                        <option value="deduction_method" {{ old('calculation_method', $salary->calculation_method) === 'deduction_method' ? 'selected' : '' }}>
                                            المرتب - خصم أيام الغياب
                                        </option>
                                    </select>
                                    @error('calculation_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Attendance -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-calendar-check"></i> تفاصيل الحضور والغياب
                            </h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>أيام الحضور <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="attendance_days" class="form-control @error('attendance_days') is-invalid @enderror"
                                           value="{{ old('attendance_days', $salary->attendance_days) }}" min="0" max="31" required onchange="calculateSalary()">
                                    @error('attendance_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>أيام الغياب <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="absence_days" class="form-control @error('absence_days') is-invalid @enderror"
                                           value="{{ old('absence_days', $salary->absence_days) }}" min="0" max="31" required onchange="calculateSalary()">
                                    @error('absence_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>أيام التأخير <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="late_days" class="form-control @error('late_days') is-invalid @enderror"
                                           value="{{ old('late_days', $salary->late_days) }}" min="0" max="31" required>
                                    @error('late_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>أيام الإجازة <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="leave_days" class="form-control @error('leave_days') is-invalid @enderror"
                                           value="{{ old('leave_days', $salary->leave_days) }}" min="0" max="31" required>
                                    @error('leave_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Calculation Preview -->
                        <div class="mb-4">
                            <div class="alert alert-info">
                                <h6 style="margin-top: 0;"><i class="fas fa-calculator"></i> معاينة الحساب</h6>
                                <div id="calculationPreview">
                                    <p>المرتب الأساسي: <strong id="previewBaseSalary">{{ $salary->base_salary }}</strong> ج.م</p>
                                    <p>أيام الحضور: <strong id="previewAttendance">{{ $salary->attendance_days }}</strong></p>
                                    <p>أيام الغياب: <strong id="previewAbsence">{{ $salary->absence_days }}</strong></p>
                                    <p style="border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">
                                        المرتب المحسوب: <strong style="color: #2196f3; font-size: 1.1rem;" id="previewCalculated">
                                            {{ number_format($salary->calculated_salary, 2) }}
                                        </strong> ج.م
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Allowances Section -->
                        <div class="mb-4">
                            <h6 style="color: #ff9800; font-weight: 700; border-bottom: 2px solid #ff9800; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-plus"></i> إضافة خصم أو علاوة
                            </h6>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>النوع</strong></label>
                                    <select id="allowanceType" class="form-select">
                                        <option value="">-- اختر --</option>
                                        <option value="deduction">خصم</option>
                                        <option value="bonus">علاوة</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>البيان</strong></label>
                                    <input type="text" id="allowanceName" class="form-control" placeholder="مثال: تأمينات، غياب بدون إجازة">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>المبلغ (ج.م)</strong></label>
                                    <input type="number" id="allowanceAmount" class="form-control" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label"><strong>الوصف</strong></label>
                                    <textarea id="allowanceDescription" class="form-control" rows="2" placeholder="ملاحظات اختيارية"></textarea>
                                </div>
                            </div>

                            <button type="button" class="btn btn-info btn-sm" onclick="addAllowanceForm()">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                        </div>

                        <!-- Existing Allowances -->
                        @if($salary->allowances->count() > 0)
                        <div class="mb-4">
                            <h6 style="color: #666; font-weight: 700; margin-bottom: 15px;">الخصومات والعلاوات الحالية</h6>
                            <div id="allowancesList" class="list-group">
                                @foreach($salary->allowances as $allowance)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $allowance->name }}</strong>
                                        @if($allowance->type === 'deduction')
                                            <span class="badge bg-danger">خصم</span>
                                        @else
                                            <span class="badge bg-success">علاوة</span>
                                        @endif
                                        <br>
                                        <small class="text-muted">{{ $allowance->description }}</small>
                                    </div>
                                    <div>
                                        <strong>{{ number_format($allowance->amount, 2) }} ج.م</strong>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Notes -->
                        <div class="mb-4">
                            <label class="form-label"><strong>ملاحظات</strong></label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات اضافية">{{ old('notes', $salary->notes) }}</textarea>
                        </div>

                        <!-- Submit -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success" style="background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%); border: none;">
                                <i class="fas fa-save"></i> حفظ التغييرات
                            </button>
                            <a href="{{ route('hr.salaries.show', $salary) }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function calculateSalary() {
    const baseSalary = parseFloat(document.querySelector('input[name="base_salary"]').value) || 0;
    const attendanceDays = parseInt(document.querySelector('input[name="attendance_days"]').value) || 0;
    const absenceDays = parseInt(document.querySelector('input[name="absence_days"]').value) || 0;
    const method = document.querySelector('select[name="calculation_method"]').value;

    let calculated = 0;
    const dailyRate = baseSalary / 30;

    if (method === 'daily_rate') {
        calculated = dailyRate * attendanceDays;
    } else {
        calculated = baseSalary - (dailyRate * absenceDays);
    }

    document.getElementById('previewBaseSalary').textContent = baseSalary.toFixed(2);
    document.getElementById('previewAttendance').textContent = attendanceDays;
    document.getElementById('previewAbsence').textContent = absenceDays;
    document.getElementById('previewCalculated').textContent = calculated.toFixed(2);
}

function addAllowanceForm() {
    const type = document.getElementById('allowanceType').value;
    const name = document.getElementById('allowanceName').value;
    const amount = document.getElementById('allowanceAmount').value;
    const description = document.getElementById('allowanceDescription').value;

    if (!type || !name || !amount) {
        alert('يرجى ملء جميع الحقول المطلوبة');
        return;
    }

    // Create form to submit allowance
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("hr.salaries.allowance", $salary) }}';
    form.innerHTML = `
        @csrf
        <input type="hidden" name="type" value="${type}">
        <input type="hidden" name="name" value="${name}">
        <input type="hidden" name="amount" value="${amount}">
        <input type="hidden" name="description" value="${description}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Calculate on page load
window.addEventListener('DOMContentLoaded', calculateSalary);
</script>

@endsection
