@php
    $recentCustodies = App\Models\Custody::with('user', 'treasury')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    $recentExpenses = App\Models\Expense::with('user', 'custody')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    $recentCases = App\Models\SocialCase::with('researcher')
        ->where('status', '!=', 'new')
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();
@endphp

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 style="margin: 0;"><i class="fas fa-hand-holding-heart"></i> أحدث العهد</h6>
            </div>
            <div class="card-body p-0">
                @forelse($recentCustodies as $custody)
                    <div class="activity-item" style="margin-bottom: 0.75rem; border-left: 4px solid #667eea;">
                        <div class="activity-title">
                            <a href="{{ route('custodies.show', $custody->id) }}" style="color: var(--primary); text-decoration: none;">
                                عهدة #{{ $custody->id }}
                            </a>
                        </div>
                        <div class="activity-desc">
                            <strong>{{ $custody->user->name }}</strong> - {{ number_format($custody->amount, 2) }} ج.م
                        </div>
                        <div class="activity-time">
                            <i class="fas fa-calendar"></i> {{ $custody->created_at->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <div class="p-3 text-center text-muted">لا توجد عهد حديثة</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 style="margin: 0;"><i class="fas fa-money-bill-wave"></i> أحدث المصروفات</h6>
            </div>
            <div class="card-body p-0">
                @forelse($recentExpenses as $expense)
                    <div class="activity-item" style="margin-bottom: 0.75rem; border-left: 4px solid #f5576c;">
                        <div class="activity-title">
                            <a href="{{ route('expenses.show', $expense->id) }}" style="color: #f5576c; text-decoration: none;">
                                مصروف #{{ $expense->id }}
                            </a>
                        </div>
                        <div class="activity-desc">
                            <strong>{{ $expense->user->name }}</strong> - {{ number_format($expense->amount, 2) }} ج.م
                        </div>
                        <div class="activity-time">
                            <i class="fas fa-calendar"></i> {{ $expense->created_at->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <div class="p-3 text-center text-muted">لا توجد مصروفات حديثة</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 style="margin: 0;"><i class="fas fa-file-alt"></i> أحدث الحالات الاجتماعية</h6>
            </div>
            <div class="card-body p-0">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; padding: 1rem;">
                    @forelse($recentCases as $case)
                        <div class="activity-item" style="margin: 0;">
                            <div class="activity-title">
                                <a href="{{ route('social_cases.show', $case->id) }}" style="color: var(--primary); text-decoration: none;">
                                    {{ $case->name }}
                                </a>
                            </div>
                            <div class="activity-desc">
                                <strong>{{ $case->researcher?->name ?? 'بدون باحث' }}</strong>
                            </div>
                            <div class="activity-time">
                                <span class="badge bg-{{ $case->status === 'approved' ? 'success' : ($case->status === 'pending' ? 'warning' : 'danger') }}">
                                    @switch($case->status)
                                        @case('pending')
                                            قيد الانتظار
                                            @break
                                        @case('approved')
                                            موافق عليه
                                            @break
                                        @case('rejected')
                                            مرفوض
                                            @break
                                        @default
                                            {{ $case->status }}
                                    @endswitch
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="p-3 text-center text-muted">لا توجد حالات حديثة</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
