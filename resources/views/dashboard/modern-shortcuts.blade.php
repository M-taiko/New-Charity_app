@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Welcome Header -->
    <div class="row mb-5">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; color: white;">
                <div>
                    <h1 style="margin: 0; font-size: 2.5rem; font-weight: 800;">
                        أهلاً وسهلاً، {{ auth()->user()->name }} 👋
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; font-size: 1.1rem; opacity: 0.9;">
                        {{ auth()->user()->getRoleNames()->first() ?? 'مستخدم' }}
                    </p>
                </div>
                <div style="font-size: 4rem;">
                    @if(auth()->user()->hasRole('محاسب'))
                        💰
                    @elseif(auth()->user()->hasRole('مدير'))
                        👔
                    @elseif(auth()->user()->hasRole('باحث اجتماعي'))
                        📋
                    @elseif(auth()->user()->hasRole('مندوب'))
                        🚀
                    @else
                        👤
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-5">
        @if(auth()->user()->hasRole('محاسب'))
            <!-- Stats للمحاسب -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #36befd65 100%); color: rgb(0, 0, 0);">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-label">الرصيد الحالي</div>
                    <div class="stat-number">{{ number_format($treasury?->balance ?? 0, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <div class="stat-label">إجمالي العهد</div>
                    <div class="stat-number">{{ number_format($totalCustodiesAmount, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-label">إجمالي المصروفات <span class="badge bg-light text-dark" style="font-size: 0.6rem;">يتأثر بالتوجيه</span></div>
                    <div class="stat-number" id="dashTotalExpenses">{{ number_format($totalSpent, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
                    <div class="stat-label">إجمالي الخزينة والعهد</div>
                    <div class="stat-number">{{ number_format($totalAssets, 2) }} ج.م</div>
                </div>
            </div>

        @elseif(auth()->user()->hasRole('مدير'))
            <!-- Stats للمدير -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
                    <div class="stat-label">العهد النشطة</div>
                    <div class="stat-number">{{ $activeCustodies }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-folder"></i></div>
                    <div class="stat-label">حالات معلقة</div>
                    <div class="stat-number">{{ $pendingCases }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-label">إجمالي المناديب</div>
                    <div class="stat-number">{{ App\Models\User::role('مندوب')->count() }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="stat-label">المصروفات اليوم <span class="badge bg-light text-dark" style="font-size: 0.6rem;">يتأثر بالتوجيه</span></div>
                            <div class="stat-number" id="dashTodayExpenses">{{ number_format($todayExpenses, 2) }} ج.م</div>
                </div>
            </div>

        @elseif(auth()->user()->hasRole('باحث اجتماعي'))
            <!-- Stats للباحث الاجتماعي -->
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                    <div class="stat-label">إجمالي حالاتي</div>
                    <div class="stat-number">{{ $researcherStats['total_cases'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-label">حالات موافق عليها</div>
                    <div class="stat-number">{{ $researcherStats['approved_cases'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-hourglass"></i></div>
                    <div class="stat-label">حالات معلقة</div>
                    <div class="stat-number">{{ $researcherStats['pending_cases'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-percent"></i></div>
                    <div class="stat-label">نسبة الموافقة</div>
                    <div class="stat-number">{{ $researcherStats['approval_rate'] ?? 0 }}%</div>
                </div>
            </div>

        @elseif(auth()->user()->hasRole('مندوب'))
            <!-- Stats للمندوب -->
            @php
                $totalReceived = $agentStats['received'];
                $totalSpent = $agentStats['spent'];
                $totalReturned = $agentStats['returned'];
                $remaining = $agentStats['remaining'];
                $transferredOut = $agentStats['transferred_out'];
                $pendingReturn = $agentStats['pending_return'];
            @endphp
            <div class="col-12 col-sm-6 col-lg">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <div class="stat-label">إجمالي استلمت</div>
                    <div class="stat-number">{{ number_format($totalReceived, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-label">المصروف</div>
                    <div class="stat-number">{{ number_format($totalSpent, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
                    <div class="stat-label">المتبقي</div>
                    <div class="stat-number">{{ number_format($remaining, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg">
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-undo"></i></div>
                    <div class="stat-label">المرتجع</div>
                    <div class="stat-number">{{ number_format($totalReturned, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg">
                <div class="stat-card" style="background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                    <div class="stat-label">المحوّل لمناديب آخرين</div>
                    <div class="stat-number">{{ number_format($transferredOut, 2) }} ج.م</div>
                    @if($pendingReturn > 0)
                        <div class="stat-label" style="font-size: 0.75rem;">في انتظار الإرجاع: {{ number_format($pendingReturn, 2) }} ج.م</div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- T33: مستكشف التوجيه المحاسبي — للعرض المالي (محاسب/مدير/مشرف) --}}
    @php $isFinanceView = auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير') || auth()->user()->hasRole('مشرف'); @endphp
    @if($isFinanceView)
    <div class="mb-5" data-aos="fade-up">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="card-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                <h5 style="margin: 0; color: white;">
                    <i class="fas fa-sitemap"></i> مستكشف التوجيه المحاسبي
                </h5>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <nav id="dirTrail" aria-label="مسار التوجيه" class="small" style="line-height: 1.8;"></nav>
                    <button type="button" id="dirClear" class="btn btn-sm btn-outline-secondary" onclick="dirClearFilter()">
                        <i class="fas fa-times"></i> إلغاء التصفية
                    </button>
                    <a href="{{ route('reports.categories-analytics') }}" id="dirAnalyticsLink" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt"></i> فتح تحليل التوجيهات
                    </a>
                    <span id="dirLoading" class="text-muted small" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> جارٍ التحديث...
                    </span>
                </div>
                <div class="row">
                    <div class="col-12 col-md-5">
                        <div style="position: relative; height: 250px;">
                            <canvas id="directionExplorerChart"></canvas>
                        </div>
                    </div>
                    <div class="col-12 col-md-7">
                        <div id="directionExplorerList" style="max-height: 250px; overflow-y: auto;"></div>
                    </div>
                </div>
                <small class="text-muted d-block mt-2"><i class="fas fa-info-circle"></i> اختر توجيهاً لتصفية الأرقام والرسوم المتأثرة به في هذه الصفحة.</small>
            </div>
        </div>
    </div>
    @endif

    <!-- Charts Section (للمحاسب والمدير فقط) -->
    @if(auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير'))
    <div class="mb-5" data-aos="fade-up">
        <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">
            <i class="fas fa-chart-bar"></i> إحصائيات عامة
        </h2>

        <style>
            .chart-card {
                border-radius: 12px !important;
                overflow: hidden;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                cursor: pointer;
            }
            .chart-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15) !important;
            }
        </style>

        {{-- الرسوم المتأثرة بالتوجيه (T33) --}}
        <div class="row g-3 mb-1" id="reactingGroup">
            <!-- Expense Distribution Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-chart-pie" style="color: #667eea; margin-left: 0.5rem;"></i> توزيع المصروفات
                            <span class="badge bg-info" style="font-size: 0.6rem;">يتأثر بالتوجيه</span>
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Expenses Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-line-chart" style="color: #43e97b; margin-left: 0.5rem;"></i> المصروفات الشهرية
                            <span class="badge bg-info" style="font-size: 0.6rem;">يتأثر بالتوجيه</span>
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="monthlyExpenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expense Type Distribution Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-sitemap" style="color: #ff9800; margin-left: 0.5rem;"></i> أنواع المصروفات
                            <span class="badge bg-info" style="font-size: 0.6rem;">يتأثر بالتوجيه</span>
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="expenseTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Agents Performance -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-users" style="color: #fa709a; margin-left: 0.5rem;"></i> مصروفات المناديب
                            <span class="badge bg-info" style="font-size: 0.6rem;">يتأثر بالتوجيه</span>
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="agentsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- فاصل واضح: الأرقام الثابتة لا تتأثر بالتوجيه (T33) --}}
        <div class="d-flex align-items-center gap-2 my-3">
            <span class="badge bg-secondary"><i class="fas fa-lock"></i> الأرقام الثابتة لا تتأثر بالتوجيه</span>
            <hr class="flex-grow-1 m-0">
        </div>

        <div class="row g-3">
            <!-- Custody Status Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-tasks" style="color: #f5576c; margin-left: 0.5rem;"></i> حالة العهد
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="custodyStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Treasury Balances Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-building" style="color: #4caf50; margin-left: 0.5rem;"></i> أرصدة الخزائن
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="treasuryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Cases Expenses Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-heart" style="color: #e91e63; margin-left: 0.5rem;"></i> الحالات الاجتماعية
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="socialCasesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Cases vs Other Expenses -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-balance-scale" style="color: #2196f3; margin-left: 0.5rem;"></i> مقارنة المصروفات
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="socialVsOtherChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Cases Breakdown -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 chart-card">
                    <div class="card-body p-3">
                        <h6 class="card-title mb-2 fw-bold" style="font-size: 0.85rem;">
                            <i class="fas fa-pie-chart" style="color: #9c27b0; margin-left: 0.5rem;"></i> توزيع الحالات
                        </h6>
                        <div style="position: relative; height: 180px;">
                            <canvas id="socialCasesBreakdownChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Shortcuts Section -->
    <div class="row mb-5">
        <div class="col-12">
            <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 1.5rem;">
                <i class="fas fa-flash"></i> اختصارات سريعة
            </h2>

            @if(auth()->user()->hasRole('محاسب'))
                @include('dashboard.shortcuts.accountant')

            @elseif(auth()->user()->hasRole('مدير'))
                @include('dashboard.shortcuts.manager')

            @elseif(auth()->user()->hasRole('باحث اجتماعي'))
                @include('dashboard.shortcuts.researcher')

            @elseif(auth()->user()->hasRole('مندوب'))
                @include('dashboard.shortcuts.agent')

            @endif
        </div>
    </div>

    <!-- Recent Activities Section -->
    <div class="row">
        <div class="col-12">
            <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 1.5rem;">
                <i class="fas fa-history"></i> آخر النشاطات
            </h2>

            @if(auth()->user()->hasRole('باحث اجتماعي'))
                @include('dashboard.activities.researcher')

            @elseif(auth()->user()->hasRole('مندوب'))
                @include('dashboard.activities.agent')

            @else
                @include('dashboard.activities.admin')
            @endif
        </div>
    </div>
</div>

<style>
    .stat-card {
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        opacity: 0.9;
    }

    .stat-label {
        font-size: 0.9rem;
        opacity: 0.85;
        margin-bottom: 0.5rem;
    }

    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
    }

    .shortcut-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }

    .shortcut-card {
        padding: 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        border: 2px solid transparent;
        transition: all 0.3s ease;
        background: white;
    }

    .shortcut-card:hover {
        transform: translateY(-8px);
        border-color: #667eea;
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.15);
    }

    .shortcut-icon {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }

    .shortcut-title {
        font-weight: 700;
        margin-bottom: 0.5rem;
        font-size: 1rem;
    }

    .shortcut-desc {
        font-size: 0.85rem;
        color: #6b7280;
    }

    .activity-item {
        padding: 1rem;
        border-left: 4px solid #667eea;
        background: #f9fafb;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .activity-time {
        font-size: 0.85rem;
        color: #9ca3af;
    }

    .activity-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .activity-desc {
        font-size: 0.9rem;
        color: #6b7280;
    }
</style>

@if($isFinanceView ?? (auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير') || auth()->user()->hasRole('مشرف')))
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // T33: أرقام التوجيه الحالية من الخادم + إجمالي الخزينة والعهد (ثابت)
    const dashFigures = @json($directionFigures);
    const dashExplorerData = @json($directionExplorer);
    const dashTotalAssets = {{ $totalAssets ?? 0 }};
    const homeCharts = {};

    @if(auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير'))
    // Expense Chart (يتأثر بالتوجيه)
    const expenseCtx = document.getElementById('expenseChart');
    if (expenseCtx) {
        homeCharts.expense = new Chart(expenseCtx, {
            type: 'doughnut',
            data: {
                labels: ['المصروفات', 'الرصيد المتبقي'],
                datasets: [{
                    data: [
                        dashFigures.grand_total,
                        Math.max(dashTotalAssets - dashFigures.grand_total, 0)
                    ],
                    backgroundColor: ['#f5576c', '#43e97b'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 20 } }
                }
            }
        });
    }

    // Custody Status Chart
    const custodyCtx = document.getElementById('custodyStatusChart');
    if (custodyCtx) {
        new Chart(custodyCtx, {
            type: 'bar',
            data: {
                labels: ['نشطة', 'معلقة', 'مرفوضة', 'مغلقة'],
                datasets: [{
                    label: 'عدد العهد',
                    data: [
                        {{ \App\Models\Custody::whereIn('status', ['accepted', 'active'])->count() }},
                        {{ \App\Models\Custody::where('status', 'pending')->count() }},
                        {{ \App\Models\Custody::where('status', 'rejected')->count() }},
                        {{ \App\Models\Custody::where('status', 'closed')->count() }}
                    ],
                    backgroundColor: ['#4caf50', '#ff9800', '#f44336', '#9e9e9e'],
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Monthly Expenses Chart (يتأثر بالتوجيه)
    const monthlyCtx = document.getElementById('monthlyExpenseChart');
    if (monthlyCtx) {
        const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'];
        const data = dashFigures.by_month.slice(0, 6);

        homeCharts.monthly = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'المصروفات',
                    data: data,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Top Agents Chart — مصروفات المنديب (يتأثر بالتوجيه)
    const agentsCtx = document.getElementById('agentsChart');
    if (agentsCtx) {
        homeCharts.agents = new Chart(agentsCtx, {
            type: 'bar',
            data: {
                labels: dashFigures.by_agent.map(a => a.name),
                datasets: [{
                    label: 'المصروفات (ج.م)',
                    data: dashFigures.by_agent.map(a => a.amount),
                    backgroundColor: '#667eea',
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Treasury Balances Chart
    const treasuryCtx = document.getElementById('treasuryChart');
    if (treasuryCtx) {
        @php
            $treasuries = \App\Models\Treasury::all();
        @endphp
        new Chart(treasuryCtx, {
            type: 'bar',
            data: {
                labels: [
                    @foreach($treasuries as $treasury)
                        '{{ $treasury->name }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'الرصيد',
                    data: [
                        @foreach($treasuries as $treasury)
                            {{ $treasury->balance }},
                        @endforeach
                    ],
                    backgroundColor: ['#4caf50', '#2196f3', '#ff9800', '#9c27b0', '#f44336'],
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Social Cases Expenses Chart (Monthly)
    const socialCasesCtx = document.getElementById('socialCasesChart');
    if (socialCasesCtx) {
        @php
            $monthsAr = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
            $currentMonth = now()->month;
        @endphp
        const months = [
            @for($i = 1; $i <= $currentMonth; $i++)
                '{{ $monthsAr[$i-1] }}',
            @endfor
        ];
        const data = [
            @for($i = 1; $i <= $currentMonth; $i++)
                {{ \App\Models\Expense::whereMonth('created_at', $i)->whereYear('created_at', now()->year)->where('type', 'social_case')->sum('amount') }},
            @endfor
        ];

        new Chart(socialCasesCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'مصروفات الحالات الاجتماعية',
                    data: data,
                    borderColor: '#e91e63',
                    backgroundColor: 'rgba(233, 30, 99, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Expense Type Distribution (يتأثر بالتوجيه)
    const expenseTypeCtx = document.getElementById('expenseTypeChart');
    if (expenseTypeCtx) {
        homeCharts.type = new Chart(expenseTypeCtx, {
            type: 'doughnut',
            data: {
                labels: ['حالات اجتماعية', 'مصروفات عامة'],
                datasets: [{
                    data: [
                        dashFigures.by_type.social_case,
                        dashFigures.by_type.general
                    ],
                    backgroundColor: ['#e91e63', '#2196f3'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 20 } }
                }
            }
        });
    }

    // Social Cases vs Other Expenses
    const socialVsOtherCtx = document.getElementById('socialVsOtherChart');
    if (socialVsOtherCtx) {
        @php
            $monthsAr = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
            $currentMonth = now()->month;
        @endphp
        const months = [
            @for($i = 1; $i <= $currentMonth; $i++)
                '{{ $monthsAr[$i-1] }}',
            @endfor
        ];
        const socialData = [
            @for($i = 1; $i <= $currentMonth; $i++)
                {{ \App\Models\Expense::whereMonth('created_at', $i)->whereYear('created_at', now()->year)->where('type', 'social_case')->sum('amount') }},
            @endfor
        ];
        const otherData = [
            @for($i = 1; $i <= $currentMonth; $i++)
                {{ \App\Models\Expense::whereMonth('created_at', $i)->whereYear('created_at', now()->year)->where('type', 'general')->sum('amount') }},
            @endfor
        ];

        new Chart(socialVsOtherCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'حالات اجتماعية',
                        data: socialData,
                        backgroundColor: '#e91e63',
                        borderColor: '#fff',
                        borderWidth: 1
                    },
                    {
                        label: 'مصروفات أخرى',
                        data: otherData,
                        backgroundColor: '#2196f3',
                        borderColor: '#fff',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Social Cases Breakdown
    const socialBreakdownCtx = document.getElementById('socialCasesBreakdownChart');
    if (socialBreakdownCtx) {
        @php
            $socialCases = \App\Models\SocialCase::withCount('expenses')->orderByDesc('expenses_count')->take(5)->get();
        @endphp
        new Chart(socialBreakdownCtx, {
            type: 'bar',
            data: {
                labels: [
                    @foreach($socialCases as $case)
                        '{{ $case->name }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'عدد المصروفات',
                    data: [
                        @foreach($socialCases as $case)
                            {{ $case->expenses_count }},
                        @endforeach
                    ],
                    backgroundColor: '#9c27b0',
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
    @endif

    // ──── T33: مستكشف التوجيه + تحديث الأرقام المتأثرة ────
    const dirState = {
        direction: {{ $directionId ?: 'null' }},
        item: {{ $directionItemId ?: 'null' }},
    };
    const EXPLORER_COLORS = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#ff9800', '#9c27b0'];
    let explorerChart = null;

    function dirRows(explorer) {
        return explorer.children.length ? explorer.children : explorer.items;
    }

    function renderExplorer(explorer) {
        const rows = dirRows(explorer);
        const labels = rows.map(r => r.name);
        const amounts = rows.map(r => r.amount);

        if (!explorerChart) {
            explorerChart = new Chart(document.getElementById('directionExplorerChart'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: amounts,
                        backgroundColor: EXPLORER_COLORS.slice(0, labels.length),
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    onClick: function(e, els) {
                        if (els.length) {
                            const row = rows[els[0].index];
                            dirSelect(row.is_item ? null : row.id, row.is_item ? row.id : null);
                        }
                    },
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 10 } } }
                    }
                }
            });
        } else {
            explorerChart.data.labels = labels;
            explorerChart.data.datasets[0].data = amounts;
            explorerChart.data.datasets[0].backgroundColor = EXPLORER_COLORS.slice(0, labels.length);
            explorerChart.update();
        }
        document.getElementById('directionExplorerChart').style.cursor = rows.length ? 'pointer' : 'default';

        const total = amounts.reduce((a, b) => a + b, 0);
        let html = '';
        rows.forEach(r => {
            const share = total > 0 ? (r.amount / total * 100).toFixed(1) : '0.0';
            const selected = r.is_item && r.id == dirState.item;
            html += `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1 ${selected ? 'active' : ''}" onclick="dirSelect(${r.is_item ? 'null, ' + r.id : r.id + ', null'})">
                <span class="small">${r.name}</span>
                <span dir="ltr" class="small">${formatMoney(r.amount)} <small>(${share}%)</small></span>
            </button>`;
        });
        if (explorer.direct_amount > 0 && rows.length) {
            html += `<div class="list-group-item d-flex justify-content-between text-muted py-1 small">
                <span>مصروفات على الفئة نفسها مباشرة</span>
                <span dir="ltr">${formatMoney(explorer.direct_amount)}</span>
            </div>`;
        }
        if (!rows.length) {
            html = '<div class="text-muted small p-2">لا توجد مستويات أدنى تحت هذه العقدة — استخدم مسار التنقل للرجوع.</div>';
        }
        document.getElementById('directionExplorerList').innerHTML = html;

        let trail = '<button type="button" class="btn btn-sm btn-link p-0" onclick="dirSelect(null, null)">الكل</button>';
        explorer.trail.forEach(t => {
            trail += ' ‹ <button type="button" class="btn btn-sm btn-link p-0" onclick="dirSelect(' + t.id + ', null)">' + t.name + '</button>';
        });
        document.getElementById('dirTrail').innerHTML = trail;

        document.getElementById('dirAnalyticsLink').href =
            '{{ route('reports.categories-analytics') }}' + (dirState.direction ? '?category_id=' + dirState.direction : '');
    }

    function applyFigures(figures) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = formatMoney(v) + ' ج.م'; };
        set('dashTotalExpenses', figures.grand_total);
        set('dashTodayExpenses', figures.today);

        if (homeCharts.expense) {
            homeCharts.expense.data.datasets[0].data = [figures.grand_total, Math.max(dashTotalAssets - figures.grand_total, 0)];
            homeCharts.expense.update();
        }
        if (homeCharts.monthly) {
            homeCharts.monthly.data.datasets[0].data = figures.by_month.slice(0, 6);
            homeCharts.monthly.update();
        }
        if (homeCharts.type) {
            homeCharts.type.data.datasets[0].data = [figures.by_type.social_case, figures.by_type.general];
            homeCharts.type.update();
        }
        if (homeCharts.agents) {
            homeCharts.agents.data.labels = figures.by_agent.map(a => a.name);
            homeCharts.agents.data.datasets[0].data = figures.by_agent.map(a => a.amount);
            homeCharts.agents.update();
        }
    }

    async function dirSelect(categoryId, itemId) {
        dirState.direction = categoryId;
        dirState.item = itemId;
        document.getElementById('dirLoading').style.display = '';
        try {
            const params = new URLSearchParams();
            if (categoryId) params.set('direction', categoryId);
            if (itemId) params.set('item', itemId);
            const res = await fetch('{{ route('api.directional-stats') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            applyFigures(data.figures);
            renderExplorer(data.explorer);

            // حفظ التصفية في رابط الصفحة: التحديث أو المشاركة يعيدان نفس الشاشة
            const url = new URL(window.location.href);
            ['direction', 'item'].forEach(k => url.searchParams.delete(k));
            if (categoryId) url.searchParams.set('direction', categoryId);
            if (itemId) url.searchParams.set('item', itemId);
            history.replaceState(null, '', url);
        } catch (e) {
            console.error('فشل تحديث أرقام التوجيه', e);
        } finally {
            document.getElementById('dirLoading').style.display = 'none';
        }
    }
    window.dirSelect = dirSelect;

    function dirClearFilter() { dirSelect(null, null); }
    window.dirClearFilter = dirClearFilter;

    // العرض الأول من الخادم — نفس أرقام نقطة JSON
    renderExplorer(dashExplorerData);
</script>
@endif

@endsection
