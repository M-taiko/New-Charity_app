@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-chart-bar"></i> التقارير والإحصائيات
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        ملخصات شاملة لعمليات المؤسسة
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Treasury -->
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div class="stat-label">رصيد الخزينة</div>
                <div class="stat-number" style="color: var(--success);">
                    {{ number_format(\App\Models\Treasury::first()?->balance ?? 0, 0) }}
                </div>
                <small style="color: #6b7280;">ج.م</small>
            </div>
        </div>

        <!-- Total Expenses -->
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-label">إجمالي المصروفات</div>
                <div class="stat-number" style="color: var(--danger);">
                    {{ number_format(\App\Models\Expense::sum('amount'), 0) }}
                </div>
                <small style="color: #6b7280;">ج.م</small>
            </div>
        </div>

        <!-- Active Custodies -->
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="stat-label">العهد النشطة</div>
                <div class="stat-number" style="color: var(--info);">
                    {{ \App\Models\Custody::where('status', 'accepted')->count() }}
                </div>
            </div>
        </div>

        <!-- Social Cases -->
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-people-group"></i></div>
                <div class="stat-label">الحالات الاجتماعية</div>
                <div class="stat-number" style="color: var(--warning);">
                    {{ \App\Models\SocialCase::count() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports Section -->
    <div class="row g-4">
        <!-- Custody Report -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="400">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-hand-holding-heart"></i> تقرير العهد
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي العهد الصادرة</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #667eea;">
                                    {{ number_format(\App\Models\Custody::sum('amount'), 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي المصروف</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #f57c00;">
                                    {{ number_format(\App\Models\Custody::sum('spent'), 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                    </div>
                    <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                        <small style="color: #666;">إجمالي المردود</small>
                        <h4 style="margin: 0.5rem 0 0 0; color: #4caf50;">
                            {{ number_format(\App\Models\Custody::sum('returned'), 0) }}
                        </h4>
                        <small style="color: #999;">ج.م</small>
                    </div>
                    <div style="margin-top: 1rem;">
                        <a href="{{ route('custodies.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> عرض جميع العهد
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Cases Report -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="500">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-people-group"></i> تقرير الحالات الاجتماعية
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">حالات موافق عليها</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #4caf50;">
                                    {{ \App\Models\SocialCase::where('status', 'approved')->count() }}
                                </h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">حالات قيد الانتظار</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #ff9800;">
                                    {{ \App\Models\SocialCase::where('status', 'pending')->count() }}
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                        <small style="color: #666;">إجمالي المبلغ المصروف</small>
                        <h4 style="margin: 0.5rem 0 0 0; color: #2196f3;">
                            {{ number_format(\App\Models\Expense::where('type', 'social_case')->sum('amount'), 0) }}
                        </h4>
                        <small style="color: #999;">ج.م</small>
                    </div>
                    <div style="margin-top: 1rem;">
                        <a href="{{ route('social_cases.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> عرض جميع الحالات
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expenses Summary -->
    <div class="row g-4 mt-2">
        <div class="col-12" data-aos="fade-up" data-aos-delay="600">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-receipt"></i> ملخص المصروفات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 col-md-3">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">مصروفات اليوم</small>
                                <h4 style="margin: 0; color: #f5576c;">
                                    {{ number_format(\App\Models\Expense::whereDate('created_at', \Carbon\Carbon::today())->sum('amount'), 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">مصروفات هذا الشهر</small>
                                <h4 style="margin: 0; color: #f5576c;">
                                    {{ number_format(\App\Models\Expense::whereMonth('created_at', \Carbon\Carbon::now()->month)->sum('amount'), 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">مصروفات هذه السنة</small>
                                <h4 style="margin: 0; color: #f5576c;">
                                    {{ number_format(\App\Models\Expense::whereYear('created_at', \Carbon\Carbon::now()->year)->sum('amount'), 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">إجمالي جميع المصروفات</small>
                                <h4 style="margin: 0; color: #f5576c;">
                                    {{ number_format(\App\Models\Expense::sum('amount'), 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
