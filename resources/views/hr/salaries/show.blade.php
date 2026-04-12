@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-user-money"></i> تفاصيل المرتب
                    </h1>
                </div>
                <div>
                    <a href="{{ route('hr.salaries.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> رجوع
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <!-- Salary Details Card -->
            <div class="card mb-3">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-info-circle"></i> بيانات المرتب
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>اسم الموظف:</strong></label>
                            <p>{{ $salary->employee->user->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>الوظيفة:</strong></label>
                            <p>{{ $salary->employee->job_title }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>الشهر والسنة:</strong></label>
                            <p>{{ $salary->month }}/{{ $salary->year }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>طريقة الحساب:</strong></label>
                            <p>
                                @if($salary->calculation_method === 'daily_rate')
                                    المرتب / أيام العمل × أيام الحضور
                                @else
                                    المرتب - خصم أيام الغياب
                                @endif
                            </p>
                        </div>
                    </div>

                    <hr>

                    <!-- Attendance Details -->
                    <h6 style="color: #4facfe; font-weight: 700; margin-bottom: 15px;">
                        <i class="fas fa-calendar-check"></i> تفاصيل الحضور والغياب
                    </h6>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="text-center p-3" style="background: #f0f7ff; border-radius: 8px;">
                                <h5 style="color: #4facfe; margin: 0;">{{ $salary->attendance_days }}</h5>
                                <small>أيام الحضور</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3" style="background: #fff3e0; border-radius: 8px;">
                                <h5 style="color: #ff9800; margin: 0;">{{ $salary->late_days }}</h5>
                                <small>أيام تأخير</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3" style="background: #ffe0e0; border-radius: 8px;">
                                <h5 style="color: #f44336; margin: 0;">{{ $salary->absence_days }}</h5>
                                <small>أيام الغياب</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-3" style="background: #e8f5e9; border-radius: 8px;">
                                <h5 style="color: #4caf50; margin: 0;">{{ $salary->leave_days }}</h5>
                                <small>أيام الإجازة</small>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Salary Calculation Details -->
                    <h6 style="color: #4facfe; font-weight: 700; margin-bottom: 15px;">
                        <i class="fas fa-calculator"></i> تفاصيل الحساب
                    </h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>المرتب الأساسي:</strong></label>
                            <div class="alert alert-info mb-0">
                                <h5 style="margin: 0;">{{ number_format($salary->base_salary, 2) }} ج.م</h5>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>المرتب المحسوب:</strong></label>
                            <div class="alert alert-primary mb-0">
                                <h5 style="margin: 0;">{{ number_format($salary->calculated_salary, 2) }} ج.م</h5>
                            </div>
                        </div>
                    </div>

                    @if($salary->deductions > 0 || $salary->bonuses > 0)
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>الخصومات:</strong></label>
                            <div class="alert alert-warning mb-0">
                                <h5 style="margin: 0; color: #f57c00;">-{{ number_format($salary->deductions, 2) }} ج.م</h5>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><strong>العلاوات:</strong></label>
                            <div class="alert alert-success mb-0">
                                <h5 style="margin: 0; color: #388e3c;">+{{ number_format($salary->bonuses, 2) }} ج.م</h5>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="row mt-3">
                        <div class="col-12">
                            <div style="background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%); color: white; padding: 20px; border-radius: 8px; text-align: center;">
                                <p style="margin: 0; font-size: 0.9rem;">المرتب النهائي</p>
                                <h3 style="margin: 10px 0 0 0;">{{ number_format($salary->final_salary, 2) }} ج.م</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Allowances (Deductions & Bonuses) -->
            @if($salary->allowances->count() > 0)
            <div class="card mb-3">
                <div class="card-header" style="background: linear-gradient(135deg, #ff9800 0%, #ffc107 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-list"></i> الخصومات والعلاوات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th>البيان</th>
                                    <th>المبلغ</th>
                                    <th>الوصف</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salary->allowances as $allowance)
                                <tr>
                                    <td>
                                        @if($allowance->type === 'deduction')
                                            <span class="badge bg-danger">خصم</span>
                                        @else
                                            <span class="badge bg-success">علاوة</span>
                                        @endif
                                    </td>
                                    <td><strong>{{ $allowance->name }}</strong></td>
                                    <td>
                                        @if($allowance->type === 'deduction')
                                            <span style="color: #f44336;">-{{ number_format($allowance->amount, 2) }}</span>
                                        @else
                                            <span style="color: #4caf50;">+{{ number_format($allowance->amount, 2) }}</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $allowance->description }}</small></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            <!-- Notes -->
            @if($salary->notes)
            <div class="card mb-3">
                <div class="card-header" style="background: #f5f5f5; border-bottom: 1px solid #ddd;">
                    <h6 style="margin: 0; color: #333;">
                        <i class="fas fa-sticky-note"></i> ملاحظات
                    </h6>
                </div>
                <div class="card-body">
                    <p>{{ $salary->notes }}</p>
                </div>
            </div>
            @endif
        </div>

        <!-- Status and Actions -->
        <div class="col-lg-4">
            <!-- Status Card -->
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle"></i> حالة المرتب
                    </h6>
                    <div style="font-size: 0.9rem;">
                        @if($salary->status === 'draft')
                            <span class="badge bg-secondary" style="font-size: 1rem; padding: 0.5rem 1rem;">مسودة</span>
                        @elseif($salary->status === 'calculated')
                            <span class="badge bg-info" style="font-size: 1rem; padding: 0.5rem 1rem;">محسوب</span>
                        @elseif($salary->status === 'approved')
                            <span class="badge bg-warning" style="font-size: 1rem; padding: 0.5rem 1rem;">موافق عليه</span>
                        @elseif($salary->status === 'paid')
                            <span class="badge bg-success" style="font-size: 1rem; padding: 0.5rem 1rem;">مدفوع</span>
                        @endif

                        <div class="mt-3">
                            @if($salary->calculated_at)
                                <p><strong>حساب في:</strong><br>{{ $salary->calculated_at->format('Y-m-d H:i') }}</p>
                            @endif

                            @if($salary->approved_at)
                                <p><strong>موافقة في:</strong><br>{{ $salary->approved_at->format('Y-m-d H:i') }}</p>
                            @endif

                            @if($salary->paid_at)
                                <p><strong>دفع في:</strong><br>{{ $salary->paid_at->format('Y-m-d H:i') }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            @if($salary->status !== 'paid')
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-cogs"></i> الإجراءات
                    </h6>

                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('hr.salaries.edit', $salary) }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> تعديل
                        </a>

                        @if($salary->status === 'calculated')
                        <form action="{{ route('hr.salaries.approve', $salary) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm w-100">
                                <i class="fas fa-check"></i> الموافقة
                            </button>
                        </form>
                        @endif

                        @if($salary->status === 'approved')
                        <form action="{{ route('hr.salaries.record-expense', $salary) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <i class="fas fa-save"></i> تسجيل كمصروف
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
