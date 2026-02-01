@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700; color: var(--dark);">
                        <i class="fas fa-dashboard"></i> لوحة التحكم
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        أهلاً بك {{ auth()->user()->name }}, إليك ملخص الأنشطة الحالية
                    </p>
                </div>
                <div style="font-size: 3rem; opacity: 0.1; color: var(--primary);">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Row -->
    @if(auth()->user()->hasRole('مندوب'))
        <!-- Agent Statistics -->
        @php
            $agentCustodies = \App\Models\Custody::where('agent_id', auth()->id())->get();
            $totalReceived = $agentCustodies->sum('amount');
            $totalSpent = $agentCustodies->sum('spent');
            $totalReturned = $agentCustodies->sum('returned');
            $actualSpent = $totalSpent - $totalReturned;
            $totalRemaining = $agentCustodies->sum(function($c) {
                return $c->amount - ($c->spent - $c->returned);
            });
        @endphp
        <div class="row g-4 mb-4">
            <!-- Total Custodies -->
            <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="0">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="stat-label">إجمالي العهد</div>
                    <div class="stat-number" style="color: var(--success);">
                        {{ number_format($totalReceived, 0) }}
                    </div>
                    <small style="color: #6b7280;">ر.س</small>
                </div>
            </div>

            <!-- Actual Spent -->
            <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-label">المصروفات الفعلية</div>
                    <div class="stat-number" style="color: var(--danger);">
                        {{ number_format($actualSpent, 0) }}
                    </div>
                    <small style="color: #6b7280;">ر.س</small>
                </div>
            </div>

            <!-- Total Remaining -->
            <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-label">المبلغ المتبقي</div>
                    <div class="stat-number" style="color: var(--info);">
                        {{ number_format($totalRemaining, 0) }}
                    </div>
                    <small style="color: #6b7280;">ر.س</small>
                </div>
            </div>

            <!-- Returned Amount -->
            <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-label">المبالغ المردودة</div>
                    <div class="stat-number" style="color: var(--warning);">
                        {{ number_format($totalReturned, 0) }}
                    </div>
                    <small style="color: #6b7280;">ر.س</small>
                </div>
            </div>
        </div>
    @else
        <!-- Admin/Accountant Statistics -->
        <div class="row g-4 mb-4">
            <!-- Treasury Balance -->
            <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="0">
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stat-label">رصيد الخزينة</div>
                    <div class="stat-number" style="color: var(--success);">
                        {{ number_format($treasury->balance ?? 0, 0) }}
                    </div>
                    <small style="color: #6b7280;">ر.س</small>
                </div>
            </div>

            <!-- Active Custodies -->
            <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="stat-label">العهد النشطة</div>
                    <div class="stat-number" style="color: var(--info);">{{ $activeCustodies }}</div>
                </div>
            </div>

            <!-- Pending Cases -->
            <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass"></i>
                    </div>
                    <div class="stat-label">الحالات المعلقة</div>
                    <div class="stat-number" style="color: var(--warning);">{{ $pendingCases }}</div>
                </div>
            </div>

            <!-- Today Expenses -->
            <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill"></i>
                    </div>
                    <div class="stat-label">مصروفات اليوم</div>
                    <div class="stat-number" style="color: var(--danger);">
                        {{ number_format($todayExpenses, 0) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Charts and Content Row -->
    <div class="row g-4">
        @if(auth()->user()->hasRole('مندوب'))
            <!-- Agent's Custodies Summary -->
            <div class="col-12 col-lg-8" data-aos="fade-up" data-aos-delay="400">
                <div class="card">
                    <div class="card-header">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h5 style="margin: 0;">
                                    <i class="fas fa-list"></i> ملخص عهدي
                                </h5>
                            </div>
                            <a href="{{ route('agent.transactions') }}" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left"></i> عرض الكل
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i> العهدة</th>
                                        <th><i class="fas fa-money-bill"></i> المبلغ</th>
                                        <th><i class="fas fa-chart-pie"></i> الصرف</th>
                                        <th><i class="fas fa-wallet"></i> المتبقي</th>
                                        <th><i class="fas fa-signal"></i> الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($agentCustodies as $custody)
                                        @php
                                            $actualSpent = $custody->getTotalSpent() - $custody->returned;
                                            $remaining = $custody->amount - $actualSpent;
                                        @endphp
                                        <tr>
                                            <td><a href="{{ route('custodies.show', $custody->id) }}" style="text-decoration: none; color: var(--primary); font-weight: 600;">عهدة #{{ $custody->id }}</a></td>
                                            <td><strong>{{ number_format($custody->amount, 0) }}</strong> ر.س</td>
                                            <td><span style="color: #e53935;">{{ number_format($actualSpent, 0) }}</span> ر.س</td>
                                            <td><span style="color: #4caf50;">{{ number_format($remaining, 0) }}</span> ر.س</td>
                                            <td>
                                                @switch($custody->status)
                                                    @case('pending')
                                                        <span class="badge bg-warning">قيد الانتظار</span>
                                                        @break
                                                    @case('accepted')
                                                        <span class="badge bg-success">نشطة</span>
                                                        @break
                                                    @case('pending_return')
                                                        <span class="badge bg-info">قيد الموافقة</span>
                                                        @break
                                                    @case('closed')
                                                        <span class="badge bg-secondary">مغلقة</span>
                                                        @break
                                                    @default
                                                        <span class="badge bg-danger">{{ $custody->status }}</span>
                                                @endswitch
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center py-5">
                                                <div class="empty-state">
                                                    <div class="empty-state-icon">
                                                        <i class="fas fa-inbox"></i>
                                                    </div>
                                                    <div class="empty-state-title">لا توجد عهد</div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Recent Transactions (Admin/Accountant) -->
            <div class="col-12 col-lg-8" data-aos="fade-up" data-aos-delay="400">
                <div class="card">
                    <div class="card-header">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h5 style="margin: 0;">
                                    <i class="fas fa-history"></i> آخر العمليات
                                </h5>
                            </div>
                            <a href="{{ route('treasury.index') }}" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left"></i> عرض الكل
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><i class="fas fa-check-circle"></i> النوع</th>
                                        <th><i class="fas fa-money-bill"></i> المبلغ</th>
                                        <th><i class="fas fa-calendar"></i> التاريخ</th>
                                        <th><i class="fas fa-user"></i> المستخدم</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse(\App\Models\TreasuryTransaction::latest()->limit(5)->get() as $transaction)
                                        <tr>
                                            <td>
                                                @switch($transaction->type)
                                                    @case('donation')
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-gift"></i> تبرع
                                                        </span>
                                                        @break
                                                    @case('expense')
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-money-bill-wave"></i> مصروف
                                                        </span>
                                                        @break
                                                    @case('custody_out')
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-arrow-up"></i> عهدة صرف
                                                        </span>
                                                        @break
                                                    @case('custody_return')
                                                        <span class="badge bg-primary">
                                                            <i class="fas fa-arrow-down"></i> عهدة إرجاع
                                                        </span>
                                                        @break
                                                @endswitch
                                            </td>
                                            <td class="fw-bold">{{ number_format($transaction->amount, 0) }} ر.س</td>
                                            <td>{{ $transaction->created_at->format('Y-m-d') }}</td>
                                            <td>
                                                <small class="badge bg-light text-dark">
                                                    {{ $transaction->user->name ?? '-' }}
                                                </small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-5">
                                                <div class="empty-state">
                                                    <div class="empty-state-icon">
                                                        <i class="fas fa-inbox"></i>
                                                    </div>
                                                    <div class="empty-state-title">لا توجد عمليات</div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Notifications & Quick Actions -->
        <div class="col-12 col-lg-4">
            <!-- Notifications -->
            <div class="card mb-4" data-aos="fade-up" data-aos-delay="500">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-bell"></i> التنبيهات الأخيرة
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse(\App\Models\Notification::where('user_id', auth()->id())->latest()->limit(5)->get() as $notification)
                            @php
                                $url = '#';
                                if ($notification->related_type === 'social_case' && $notification->related_id) {
                                    $url = route('social_cases.show', $notification->related_id);
                                } elseif ($notification->related_type === 'custody' && $notification->related_id) {
                                    $url = route('custodies.show', $notification->related_id);
                                } elseif ($notification->related_type === 'expense' && $notification->related_id) {
                                    $url = route('expenses.show', $notification->related_id);
                                }
                            @endphp
                            <a href="{{ $url }}" style="text-decoration: none; color: inherit; display: block;" onclick="markNotificationAsRead(event, {{ $notification->id }})">
                                <div class="list-group-item p-3 border-bottom-0" style="border-bottom: 1px solid var(--border); cursor: pointer; transition: all 0.2s ease;">
                                    <div style="display: flex; gap: 1rem;">
                                        <div style="width: 12px; height: 12px; background: var(--primary); border-radius: 50%; margin-top: 0.35rem; flex-shrink: 0;"></div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-weight: 600; color: var(--dark); font-size: 0.95rem;">
                                                {{ $notification->title }}
                                            </div>
                                            <div style="font-size: 0.85rem; color: #6b7280; margin-top: 0.25rem;">
                                                {{ $notification->message }}
                                            </div>
                                            <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.5rem;">
                                                <i class="fas fa-clock"></i>
                                                {{ $notification->created_at->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="p-4 text-center" style="color: #6b7280;">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>لا توجد تنبيهات جديدة</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card" data-aos="fade-up" data-aos-delay="600">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-bolt"></i> إجراءات سريعة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @if(auth()->user()->hasRole('مندوب'))
                            <!-- Agent Quick Actions -->
                            <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> تسجيل مصروف جديد
                            </a>
                            <a href="{{ route('expenses.agent') }}" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> عرض مصروفاتي
                            </a>
                            <a href="{{ route('agent.transactions') }}" class="btn btn-outline-primary">
                                <i class="fas fa-exchange-alt"></i> حركاتي من الخزينة
                            </a>
                        @else
                            <!-- Admin/Accountant Quick Actions -->
                            @can('create_custody')
                            <a href="{{ route('custodies.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> إنشاء عهدة جديدة
                            </a>
                            @endcan

                            @can('spend_money')
                            <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> تسجيل مصروف
                            </a>
                            @endcan

                            @can('create_social_case')
                            <a href="{{ route('social_cases.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> إنشاء حالة اجتماعية
                            </a>
                            @endcan

                            @can('manage_treasury')
                            <a href="{{ route('treasury.index') }}" class="btn btn-outline-primary">
                                <i class="fas fa-eye"></i> عرض الخزينة
                            </a>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .stat-card.success { border-top-color: var(--success) !important; }
    .stat-card.info { border-top-color: var(--info) !important; }
    .stat-card.warning { border-top-color: var(--warning) !important; }
    .stat-card.danger { border-top-color: var(--danger) !important; }
</style>
@endsection
