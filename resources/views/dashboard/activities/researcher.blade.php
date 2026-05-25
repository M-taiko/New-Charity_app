@php
    $researcherId = auth()->user()->id;

    $recentCases = App\Models\SocialCase::where('researcher_id', $researcherId)
        ->orderBy('updated_at', 'desc')
        ->limit(8)
        ->get();

    $pendingCases = App\Models\SocialCase::where('researcher_id', $researcherId)
        ->where('status', 'pending')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
@endphp

<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 style="margin: 0;"><i class="fas fa-list"></i> أحدث حالاتي</h6>
            </div>
            <div class="card-body p-0">
                @forelse($recentCases as $case)
                    <div class="activity-item" style="margin-bottom: 0.75rem; border-left: 4px solid #667eea;">
                        <div class="activity-title">
                            <a href="{{ route('social_cases.show', $case->id) }}" style="color: var(--primary); text-decoration: none;">
                                {{ $case->name }}
                            </a>
                        </div>
                        <div class="activity-desc">
                            نوع المساعدة: <strong>{{ $case->case_type ?? '-' }}</strong>
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                            <span class="badge bg-{{ $case->status === 'approved' ? 'success' : ($case->status === 'pending' ? 'warning' : ($case->status === 'rejected' ? 'danger' : 'secondary')) }}">
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
                                    @case('completed')
                                        مكتمل
                                        @break
                                    @default
                                        جديد
                                @endswitch
                            </span>
                            <span class="activity-time">{{ $case->updated_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="p-3 text-center text-muted">لا توجد حالات حديثة</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h6 style="margin: 0;"><i class="fas fa-hourglass"></i> الحالات المعلقة</h6>
            </div>
            <div class="card-body p-0">
                @forelse($pendingCases as $case)
                    <div class="activity-item" style="margin-bottom: 0.75rem; border-left: 4px solid #ffc107;">
                        <div class="activity-title">
                            <a href="{{ route('social_cases.show', $case->id) }}" style="color: #ff9800; text-decoration: none;">
                                {{ $case->name }}
                            </a>
                        </div>
                        <div class="activity-desc">
                            <strong>{{ $case->phone ?? 'بدون رقم' }}</strong>
                        </div>
                        <div class="activity-time">
                            <i class="fas fa-calendar"></i> {{ $case->created_at->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <div class="p-3 text-center text-muted">
                        <i class="fas fa-check-circle" style="font-size: 2rem; color: #28a745; margin-bottom: 0.5rem;"></i>
                        <div>لا توجد حالات معلقة</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
