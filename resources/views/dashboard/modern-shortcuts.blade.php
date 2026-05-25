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

@endsection
