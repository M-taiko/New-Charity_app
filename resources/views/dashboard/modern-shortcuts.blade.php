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
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
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
                    <div class="stat-label">إجمالي المصروفات</div>
                    <div class="stat-number">{{ number_format($totalSpent, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
                    <div class="stat-label">الأصول الكلية</div>
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
                    <div class="stat-label">المصروفات اليوم</div>
                    <div class="stat-number">{{ number_format($todayExpenses, 2) }} ج.م</div>
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
                $agentCustodies = auth()->user()->custodies()->whereIn('status', ['accepted', 'active', 'partially_returned'])->get();
                $totalReceived = $agentCustodies->sum('amount');
                $totalSpent = $agentCustodies->sum('spent');
                $totalReturned = $agentCustodies->sum('returned');
                $remaining = $totalReceived - $totalSpent - $totalReturned;
            @endphp
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                    <div class="stat-label">إجمالي استلمت</div>
                    <div class="stat-number">{{ number_format($totalReceived, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-label">المصروف</div>
                    <div class="stat-number">{{ number_format($totalSpent, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
                    <div class="stat-label">المتبقي</div>
                    <div class="stat-number">{{ number_format($remaining, 2) }} ج.م</div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                    <div class="stat-icon"><i class="fas fa-undo"></i></div>
                    <div class="stat-label">المرتجع</div>
                    <div class="stat-number">{{ number_format($totalReturned, 2) }} ج.م</div>
                </div>
            </div>
        @endif
    </div>

    <!-- Charts Section (للمحاسب والمدير فقط) -->
    @if(auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير'))
    <div class="mb-5" data-aos="fade-up">
        <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 2rem;">
            <i class="fas fa-chart-bar"></i> إحصائيات عامة
        </h2>

        <div class="row g-3">
            <!-- Row 1: First 3 Charts -->
            <!-- Expense Distribution Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-chart-pie" style="color: #667eea; margin-left: 0.5rem;"></i> توزيع المصروفات
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="expenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custody Status Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-tasks" style="color: #f5576c; margin-left: 0.5rem;"></i> حالة العهد
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="custodyStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Treasury Balances Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-building" style="color: #4caf50; margin-left: 0.5rem;"></i> أرصدة الخزائن
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="treasuryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Next 3 Charts -->
            <!-- Monthly Expenses Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-line-chart" style="color: #43e97b; margin-left: 0.5rem;"></i> المصروفات الشهرية
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="monthlyExpenseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Cases Expenses Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-heart" style="color: #e91e63; margin-left: 0.5rem;"></i> الحالات الاجتماعية
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="socialCasesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Cases vs Other Expenses -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-balance-scale" style="color: #2196f3; margin-left: 0.5rem;"></i> مقارنة المصروفات
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="socialVsOtherChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Last 2 Charts -->
            <!-- Expense Type Distribution Chart -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-sitemap" style="color: #ff9800; margin-left: 0.5rem;"></i> أنواع المصروفات
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="expenseTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Agents Performance -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-users" style="color: #fa709a; margin-left: 0.5rem;"></i> أفضل المناديب
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="agentsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Cases Breakdown -->
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-body p-4">
                        <h6 class="card-title mb-3 fw-bold" style="font-size: 0.95rem;">
                            <i class="fas fa-pie-chart" style="color: #9c27b0; margin-left: 0.5rem;"></i> توزيع الحالات
                        </h6>
                        <div style="position: relative; height: 250px;">
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

@if(auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير'))
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
    // Expense Chart
    const expenseCtx = document.getElementById('expenseChart');
    if (expenseCtx) {
        new Chart(expenseCtx, {
            type: 'doughnut',
            data: {
                labels: ['المصروفات', 'الرصيد المتبقي'],
                datasets: [{
                    data: [
                        {{ $totalSpent ?? 0 }},
                        {{ ($totalAssets ?? 0) - ($totalSpent ?? 0) }}
                    ],
                    backgroundColor: ['#f5576c', '#43e97b'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
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
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Monthly Expenses Chart
    const monthlyCtx = document.getElementById('monthlyExpenseChart');
    if (monthlyCtx) {
        const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'];
        const data = [
            {{ \App\Models\Expense::whereMonth('created_at', 1)->sum('amount') }},
            {{ \App\Models\Expense::whereMonth('created_at', 2)->sum('amount') }},
            {{ \App\Models\Expense::whereMonth('created_at', 3)->sum('amount') }},
            {{ \App\Models\Expense::whereMonth('created_at', 4)->sum('amount') }},
            {{ \App\Models\Expense::whereMonth('created_at', 5)->sum('amount') }},
            {{ \App\Models\Expense::whereMonth('created_at', 6)->sum('amount') }}
        ];

        new Chart(monthlyCtx, {
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
                plugins: {
                    legend: { display: true, position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Top Agents Chart
    const agentsCtx = document.getElementById('agentsChart');
    if (agentsCtx) {
        @php
            $topAgents = \App\Models\User::role('مندوب')
                ->get()
                ->map(function($agent) {
                    return (object)[
                        'name' => $agent->name,
                        'custody_count' => \App\Models\Custody::where('agent_id', $agent->id)->count()
                    ];
                })
                ->sortByDesc('custody_count')
                ->take(5);
        @endphp

        new Chart(agentsCtx, {
            type: 'bar',
            data: {
                labels: [
                    @foreach($topAgents as $agent)
                        '{{ $agent->name }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'عدد العهد',
                    data: [
                        @foreach($topAgents as $agent)
                            {{ $agent->custody_count }},
                        @endforeach
                    ],
                    backgroundColor: '#667eea',
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
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
                plugins: {
                    legend: { display: true, position: 'top' }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Expense Type Distribution
    const expenseTypeCtx = document.getElementById('expenseTypeChart');
    if (expenseTypeCtx) {
        new Chart(expenseTypeCtx, {
            type: 'doughnut',
            data: {
                labels: ['حالات اجتماعية', 'مصروفات عامة'],
                datasets: [{
                    data: [
                        {{ \App\Models\Expense::where('type', 'social_case')->sum('amount') }},
                        {{ \App\Models\Expense::where('type', 'general')->sum('amount') }}
                    ],
                    backgroundColor: ['#e91e63', '#2196f3'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
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
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
</script>
@endif

@endsection
