@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-users"></i> لوحة تحكم الموارد البشرية
            </h1>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4" data-aos="fade-up">
            <div class="card" style="border-left: 4px solid #4facfe;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div style="flex-grow: 1;">
                            <h6 class="text-muted mb-1">إجمالي الموظفين النشطين</h6>
                            <h2 class="mb-0">{{ $employeeCount }}</h2>
                        </div>
                        <i class="fas fa-users" style="font-size: 2.5rem; color: #4facfe; opacity: 0.2;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card" style="border-left: 4px solid #f093fb;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div style="flex-grow: 1;">
                            <h6 class="text-muted mb-1">إجمالي رواتب الموظفين</h6>
                            <h2 class="mb-0">{{ number_format($totalSalary, 2) }} ج.م</h2>
                        </div>
                        <i class="fas fa-money-bill-wave" style="font-size: 2.5rem; color: #f093fb; opacity: 0.2;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
            <div class="card" style="border-left: 4px solid #5edf8f;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div style="flex-grow: 1;">
                            <h6 class="text-muted mb-1">الحاضرين اليوم</h6>
                            <h2 class="mb-0">{{ $attendanceToday }}</h2>
                        </div>
                        <i class="fas fa-check-circle" style="font-size: 2.5rem; color: #5edf8f; opacity: 0.2;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card" data-aos="fade-up">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;"><i class="fas fa-users"></i> إدارة الموظفين</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">إدارة بيانات الموظفين والأقسام والرواتب</p>
                    <a href="{{ route('hr.employees.index') }}" class="btn btn-primary w-100">
                        <i class="fas fa-list"></i> عرض جميع الموظفين
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card" data-aos="fade-up" data-aos-delay="100">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                    <h5 style="margin: 0; color: white;"><i class="fas fa-calendar-check"></i> تسجيل الحضور</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">تسجيل الحضور والغياب واجازات الموظفين</p>
                    <a href="{{ route('hr.attendance.index') }}" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                        <i class="fas fa-clock"></i> إدارة الحضور
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-md-6">
            <div class="card" data-aos="fade-up">
                <div class="card-header" style="background: linear-gradient(135deg, #5edf8f 0%, #40a584 100%); border: none;">
                    <h5 style="margin: 0; color: white;"><i class="fas fa-chart-line"></i> مؤشرات الأداء (KPI)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">تتبع مؤشرات الأداء الرئيسية للموظفين</p>
                    <a href="{{ route('hr.kpi.index') }}" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #5edf8f 0%, #40a584 100%); border: none;">
                        <i class="fas fa-target"></i> إدارة KPI
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
