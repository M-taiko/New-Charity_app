@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-user-chart"></i> إحصائيات الباحثين الاجتماعيين
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        تحليل شامل لأداء الباحثين والحالات الاجتماعية
                    </p>
                </div>
            </div>
        </div>
    </div>

    @php
        $researchers = \App\Models\User::role('باحث اجتماعي')->get();
        $totalResearchers = $researchers->count();
        $totalCases = \App\Models\SocialCase::count();
        $totalSpent = \App\Models\Expense::where('type', 'social_case')->sum('amount');
        $approvedCases = \App\Models\SocialCase::where('status', 'approved')->count();
    @endphp

    <!-- Overview Cards -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-label">عدد الباحثين</div>
                <div class="stat-number" style="color: var(--primary);">{{ $totalResearchers }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
                <div class="stat-label">إجمالي الحالات</div>
                <div class="stat-number" style="color: var(--info);">{{ $totalCases }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label">حالات موافق عليها</div>
                <div class="stat-number" style="color: var(--success);">{{ $approvedCases }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-label">إجمالي المصروف</div>
                <div class="stat-number" style="color: var(--warning);">
                    {{ number_format($totalSpent, 0) }}
                </div>
                <small style="color: #6b7280;">ر.س</small>
            </div>
        </div>
    </div>

    <!-- Researchers Performance Table -->
    <div class="row" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-table"></i> أداء الباحثين الاجتماعيين
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><i class="fas fa-user"></i> اسم الباحث</th>
                                    <th><i class="fas fa-list"></i> عدد الحالات</th>
                                    <th><i class="fas fa-check"></i> حالات موافق عليها</th>
                                    <th><i class="fas fa-times"></i> حالات مرفوضة</th>
                                    <th><i class="fas fa-hourglass"></i> قيد الانتظار</th>
                                    <th><i class="fas fa-money-bill"></i> إجمالي المصروف</th>
                                    <th><i class="fas fa-chart-pie"></i> نسبة الموافقة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($researchers as $researcher)
                                    @php
                                        $researcherCases = $researcher->socialCases;
                                        $totalCasesResearcher = $researcherCases->count();
                                        $approvedResearcher = $researcherCases->where('status', 'approved')->count();
                                        $rejectedResearcher = $researcherCases->where('status', 'rejected')->count();
                                        $pendingResearcher = $researcherCases->where('status', 'pending')->count();
                                        $totalSpentResearcher = $researcherCases->sum(function($c) { return $c->getTotalSpent(); });
                                        $approvalRate = $totalCasesResearcher > 0 ? round(($approvedResearcher / $totalCasesResearcher) * 100) : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $researcher->name }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $totalCasesResearcher }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">{{ $approvedResearcher }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger">{{ $rejectedResearcher }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">{{ $pendingResearcher }}</span>
                                        </td>
                                        <td>
                                            <strong style="color: #4caf50;">{{ number_format($totalSpentResearcher, 2) }} ر.س</strong>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="flex: 1; min-width: 80px;">
                                                    <div class="progress" style="height: 6px; margin: 0;">
                                                        <div class="progress-bar bg-success" style="width: {{ $approvalRate }}%;"></div>
                                                    </div>
                                                </div>
                                                <span style="font-weight: 600; color: #4caf50; min-width: 40px;">{{ $approvalRate }}%</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <div style="color: #6b7280;">لا يوجد باحثين</div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
