@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-money-bill-wave"></i> حسابات المرتبات
            </h1>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('hr.salaries.calculate') }}" method="POST" id="calculateForm" class="row align-items-end">
                        @csrf
                        <div class="col-md-2">
                            <label class="form-label"><strong>السنة</strong></label>
                            <select name="year" id="year" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><strong>الشهر</strong></label>
                            <select name="month" id="month" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                @foreach($months as $m)
                                    <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label"><strong>طريقة الحساب</strong></label>
                            <select name="calculation_method" class="form-select">
                                <option value="daily_rate">المرتب / أيام العمل × أيام الحضور</option>
                                <option value="deduction_method">المرتب - خصم أيام الغياب</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                                <i class="fas fa-calculator"></i> حساب المرتبات
                            </button>
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('hr.salaries.export', ['year' => $year, 'month' => $month]) }}" class="btn btn-success w-100">
                                <i class="fas fa-download"></i> تصدير
                            </a>
                        </div>
                    </form>

                    <form id="filterForm" action="{{ route('hr.salaries.index') }}" method="GET" style="display: none;">
                        <input type="hidden" name="year" value="{{ $year }}">
                        <input type="hidden" name="month" value="{{ $month }}">
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Report -->
    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1)); border: 1px solid rgba(79, 172, 254, 0.3);">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h6 style="color: #666;">عدد الموظفين</h6>
                            <h3 style="color: #4facfe; margin: 0;">{{ $report['count'] }}</h3>
                        </div>
                        <div class="col-md-3">
                            <h6 style="color: #666;">إجمالي المرتبات</h6>
                            <h3 style="color: #4caf50; margin: 0;">{{ number_format($report['total_salaries'], 2) }} ج.م</h3>
                        </div>
                        <div class="col-md-3">
                            <h6 style="color: #666;">متوسط المرتب</h6>
                            <h3 style="color: #ff9800; margin: 0;">
                                {{ $report['count'] > 0 ? number_format($report['total_salaries'] / $report['count'], 2) : '0' }} ج.م
                            </h3>
                        </div>
                        <div class="col-md-3">
                            <h6 style="color: #666;">الشهر والسنة</h6>
                            <h3 style="color: #2196f3; margin: 0;">{{ $month }}/{{ $year }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Salaries Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-list"></i> تفاصيل المرتبات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>اسم الموظف</th>
                                    <th>المرتب الأساسي</th>
                                    <th>أيام الحضور</th>
                                    <th>أيام الغياب</th>
                                    <th>المرتب المحسوب</th>
                                    <th>الخصومات</th>
                                    <th>العلاوات</th>
                                    <th>المرتب النهائي</th>
                                    <th>الحالة</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($salaries as $index => $salary)
                                <tr>
                                    <td>{{ $salaries->firstItem() + $index }}</td>
                                    <td>
                                        <strong>{{ $salary->employee->user->name }}</strong>
                                    </td>
                                    <td>{{ number_format($salary->base_salary, 2) }} ج.م</td>
                                    <td>
                                        <span class="badge bg-success">{{ $salary->attendance_days }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">{{ $salary->absence_days }}</span>
                                    </td>
                                    <td>{{ number_format($salary->calculated_salary, 2) }} ج.م</td>
                                    <td>{{ number_format($salary->deductions, 2) }} ج.م</td>
                                    <td>{{ number_format($salary->bonuses, 2) }} ج.م</td>
                                    <td>
                                        <strong style="color: #4caf50; font-size: 1.1rem;">
                                            {{ number_format($salary->final_salary, 2) }} ج.م
                                        </strong>
                                    </td>
                                    <td>
                                        @if($salary->status === 'draft')
                                            <span class="badge bg-secondary">مسودة</span>
                                        @elseif($salary->status === 'calculated')
                                            <span class="badge bg-info">محسوب</span>
                                        @elseif($salary->status === 'approved')
                                            <span class="badge bg-warning">موافق عليه</span>
                                        @elseif($salary->status === 'paid')
                                            <span class="badge bg-success">مدفوع</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('hr.salaries.show', $salary) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if($salary->status !== 'paid')
                                            <a href="{{ route('hr.salaries.edit', $salary) }}" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-3"></i><br>
                                        لا توجد مرتبات محسوبة لهذا الشهر
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-4">
                        {{ $salaries->render() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Handle calculation form submission
    document.getElementById('calculateForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحساب...';

        fetch('{{ route("hr.salaries.calculate") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '{{ csrf_token() }}',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                year: formData.get('year'),
                month: formData.get('month'),
                calculation_method: formData.get('calculation_method')
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server error (${response.status}): ${text.substring(0, 200)}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('danger', data.message);
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-calculator"></i> حساب المرتبات';
        })
        .catch(error => {
            showAlert('danger', 'حدث خطأ: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-calculator"></i> حساب المرتبات';
            console.error('Salary calculation error:', error);
        });
    });

    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const container = document.querySelector('.container-fluid');
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => alertDiv.remove(), 5000);
    }
</script>

@endsection
