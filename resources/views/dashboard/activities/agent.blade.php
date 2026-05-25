@php
    $userId = auth()->user()->id;

    $recentExpenses = App\Models\Expense::where('user_id', $userId)
        ->with('custody')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    $activeCustodies = auth()->user()->custodies()
        ->whereIn('status', ['accepted', 'active', 'partially_returned'])
        ->with('treasury')
        ->orderBy('updated_at', 'desc')
        ->limit(4)
        ->get();
@endphp

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 style="margin: 0;"><i class="fas fa-money-bill-wave"></i> أحدث المصروفات</h6>
            </div>
            <div class="card-body p-0">
                @forelse($recentExpenses as $expense)
                    <div class="activity-item" style="margin-bottom: 0.75rem; border-left: 4px solid #f5576c;">
                        <div class="activity-title" style="color: #f5576c; font-weight: 600;">
                            {{ number_format($expense->amount, 2) }} ج.م
                        </div>
                        <div class="activity-desc">
                            <strong>{{ $expense->category?->name ?? $expense->description ?? 'مصروف' }}</strong>
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; font-size: 0.85rem; color: #9ca3af;">
                            <span>
                                <i class="fas fa-folder"></i>
                                {{ $expense->custody?->description ?? 'بدون عهدة' }}
                            </span>
                            <span>
                                <i class="fas fa-calendar"></i>
                                {{ $expense->created_at->format('Y-m-d') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="p-3 text-center text-muted">لا توجد مصروفات حديثة</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 style="margin: 0;"><i class="fas fa-hand-holding-heart"></i> عهدي النشطة</h6>
            </div>
            <div class="card-body p-0">
                @forelse($activeCustodies as $custody)
                    <div class="activity-item" style="margin-bottom: 0.75rem; border-left: 4px solid #43e97b;">
                        <div class="activity-title">
                            <a href="{{ route('custodies.show', $custody->id) }}" style="color: #43e97b; text-decoration: none; font-weight: 600;">
                                {{ $custody->description }}
                            </a>
                        </div>
                        <div class="activity-desc">
                            <strong>{{ number_format($custody->amount, 2) }} ج.م</strong>
                            @php
                                $remaining = $custody->amount - ($custody->spent + $custody->returned);
                            @endphp
                            | المتبقي: <span style="color: {{ $remaining < $custody->amount * 0.1 ? '#f5576c' : '#43e97b' }}; font-weight: 600;">{{ number_format($remaining, 2) }}</span> ج.م
                        </div>
                        <div style="margin-top: 0.5rem;">
                            @php
                                $percentage = ($custody->spent / $custody->amount) * 100;
                            @endphp
                            <div style="background: #e5e7eb; border-radius: 4px; height: 6px; overflow: hidden;">
                                <div style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: {{ min($percentage, 100) }}%;"></div>
                            </div>
                            <small style="color: #9ca3af; margin-top: 0.25rem; display: block;">
                                تم الإنفاق: {{ number_format($percentage, 1) }}%
                            </small>
                        </div>
                    </div>
                @empty
                    <div class="p-3 text-center text-muted">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <div>لا توجد عهد نشطة</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
