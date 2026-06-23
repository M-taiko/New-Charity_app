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

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Expenses by Date Chart -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="400">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-chart-line"></i> المصروفات آخر 7 أيام
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 300px;">
                        <canvas id="expensesByDateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses by Category Chart -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="500">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-pie-chart"></i> المصروفات حسب الفئة
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 300px;">
                        <canvas id="expensesByCategoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custodies and Cases Charts -->
    <div class="row g-4 mb-4">
        <!-- Custodies by Agents Chart -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="600">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-hand-holding-heart"></i> العهدات حسب المندوبين
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 300px;">
                        <canvas id="custodiesByAgentsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cases by Researchers Chart -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="700">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-people-group"></i> الحالات حسب الباحثين
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 300px;">
                        <canvas id="casesByResearchersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports Section -->
    <div class="row g-4">
        <!-- Custody Report -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="600">
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
                                    {{ number_format($custodyAmount, 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي المصروف</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #f57c00;">
                                    {{ number_format($custodySpent, 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي المردود</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #4caf50;">
                                    {{ number_format($custodyReturned, 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">المتبقي</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #2196f3;">
                                    {{ number_format($custodyAmount - $custodySpent, 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div style="text-align: center; padding: 10px; background: #e3f2fd; border-radius: 4px; margin-bottom: 10px;">
                                <small style="color: #1565c0;">إجمالي العهد</small>
                                <h5 style="margin: 0.3rem 0; color: #0d47a1;">{{ $totalCustodies }}</h5>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div style="text-align: center; padding: 10px; background: #f3e5f5; border-radius: 4px; margin-bottom: 10px;">
                                <small style="color: #7b1fa2;">المغلقة</small>
                                <h5 style="margin: 0.3rem 0; color: #4a148c;">{{ $closedCustodies }}</h5>
                            </div>
                        </div>
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
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="700">
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
                                    {{ $approvedCases }}
                                </h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">قيد الانتظار</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #ff9800;">
                                    {{ $pendingCases }}
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي المصروف</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #2196f3;">
                                    {{ number_format($socialCaseSpent, 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">المرفوضة</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #f44336;">
                                    {{ $rejectedCases }}
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div style="padding: 15px; background: #f5f5f5; border-radius: 4px; text-align: center;">
                        <small style="color: #666;">إجمالي الحالات</small>
                        <h4 style="margin: 0.5rem 0 0 0; color: #4facfe;">
                            {{ $totalSocialCases }}
                        </h4>
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
        <div class="col-12" data-aos="fade-up" data-aos-delay="800">
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
                                    {{ number_format($expensesToday, 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">مصروفات هذا الشهر</small>
                                <h4 style="margin: 0; color: #f5576c;">
                                    {{ number_format($expensesThisMonth, 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">مصروفات هذه السنة</small>
                                <h4 style="margin: 0; color: #f5576c;">
                                    {{ number_format($expensesThisYear, 0) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">إجمالي جميع المصروفات</small>
                                <h4 style="margin: 0; color: #f5576c;">
                                    {{ number_format($totalExpenses, 0) }}
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

@push('scripts')
<script>
    $(document).ready(function() {
        // Expenses by Date Chart
        const dateChartData = @json($expensesByDate);
        const dateLabels = dateChartData.map(d => d.date);
        const dateAmounts = dateChartData.map(d => d.amount);
        const dateFullDates = dateChartData.map(d => d.fullDate);

        new Chart(document.getElementById('expensesByDateChart'), {
            type: 'line',
            data: {
                labels: dateLabels,
                datasets: [{
                    label: 'المصروفات (ج.م)',
                    data: dateAmounts,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const date = dateFullDates[index];
                        if (date) {
                            window.location.href = `{{ route('expenses.index') }}?date=${date}`;
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: { font: { family: "'Cairo', sans-serif" } }
                    }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Change cursor for date chart
        document.getElementById('expensesByDateChart').style.cursor = 'pointer';

        // Expenses by Category Chart
        const categoryChartData = @json($expensesByCategory);
        console.log('Category Chart Data:', categoryChartData);

        const categoryLabels = categoryChartData.map(d => d.name);
        const categoryAmounts = categoryChartData.map(d => d.amount);
        const categoryIds = categoryChartData.map(d => d.id);

        console.log('Category Labels:', categoryLabels);
        console.log('Category Amounts:', categoryAmounts);

        const colors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7', '#fa709a', '#fee140'
        ];

        if (categoryLabels.length === 0) {
            console.warn('No category data available for chart');
        }

        const categoryChart = new Chart(document.getElementById('expensesByCategoryChart'), {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryAmounts,
                    backgroundColor: colors.slice(0, categoryLabels.length),
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const categoryId = categoryIds[index];
                        if (categoryId) {
                            window.location.href = `{{ route('reports.categories-analytics') }}?category_id=${categoryId}`;
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: "'Cairo', sans-serif", size: 11 } }
                    }
                }
            }
        });

        // Change cursor on hover
        document.getElementById('expensesByCategoryChart').style.cursor = 'pointer';

        // Custodies by Agents Chart - showing remaining balance after spending
        const agentsData = @json($custodiesByAgents);
        const agentLabels = agentsData.map(d => d.agent_name);
        const agentRemaining = agentsData.map(d => d.remaining);  // Show remaining after spending
        const agentIds = agentsData.map(d => d.agent_id);

        new Chart(document.getElementById('custodiesByAgentsChart'), {
            type: 'bar',
            data: {
                labels: agentLabels,
                datasets: [{
                    label: 'المبلغ المتبقي بعد الصرف (ج.م)',
                    data: agentRemaining,
                    backgroundColor: '#f093fb',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const agentId = agentIds[index];
                        if (agentId) {
                            window.location.href = `{{ route('accountant.all-custodies') }}?agent_filter=${agentId}`;
                        }
                    }
                },
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });

        document.getElementById('custodiesByAgentsChart').style.cursor = 'pointer';

        // Cases by Researchers Chart
        const researchersData = @json($casesByResearchers);
        const researcherLabels = researchersData.map(d => d.researcher_name);
        const researcherCounts = researchersData.map(d => d.cases_count);
        const researcherSpent = researchersData.map(d => d.spent);
        const researcherIds = researchersData.map(d => d.researcher_id);

        new Chart(document.getElementById('casesByResearchersChart'), {
            type: 'bar',
            data: {
                labels: researcherLabels,
                datasets: [
                    {
                        label: 'عدد الحالات',
                        data: researcherCounts,
                        backgroundColor: '#4facfe',
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'المبلغ المصروف (ج.م)',
                        data: researcherSpent,
                        backgroundColor: '#00f2fe',
                        borderRadius: 6,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const researcherId = researcherIds[index];
                        if (researcherId) {
                            window.location.href = `{{ route('social_cases.index') }}?researcher_id=${researcherId}`;
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: { font: { family: "'Cairo', sans-serif" } }
                    }
                },
                scales: {
                    x: { beginAtZero: true },
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: { display: true, text: 'العدد' }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: { display: true, text: 'المبلغ (ج.م)' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        document.getElementById('casesByResearchersChart').style.cursor = 'pointer';
    });
</script>
@endpush
@endsection
