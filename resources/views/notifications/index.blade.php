@extends('layouts.modern')

@section('content')
<div class="container-fluid">

    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 style="margin:0;font-size:2rem;font-weight:700;">
                    <i class="fas fa-bell"></i> الإشعارات
                </h1>
                @if($unreadCount > 0)
                    <small class="text-muted">{{ $unreadCount }} إشعار غير مقروء</small>
                @else
                    <small class="text-muted">جميع الإشعارات مقروءة</small>
                @endif
            </div>
            @if($unreadCount > 0)
            <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-check-double"></i> تعليم الكل كمقروء
                </button>
            </form>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row" data-aos="fade-up">
        <div class="col-12">
            @forelse($notifications as $notif)
            <div class="card mb-2 notification-card {{ !$notif->is_read ? 'unread' : '' }}"
                 style="{{ !$notif->is_read ? 'border-right:4px solid #3b82f6;background:rgba(59,130,246,.04);' : 'border-right:4px solid transparent;' }}">
                <div class="card-body py-3">
                    <div class="d-flex align-items-start gap-3">
                        <!-- Icon -->
                        <div style="width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                             background:{{ $notif->is_read ? '#f1f5f9' : 'rgba(59,130,246,.12)' }};
                             color:{{ $notif->is_read ? '#94a3b8' : '#3b82f6' }};font-size:1rem;">
                            @switch($notif->related_type)
                                @case('custody') <i class="fas fa-briefcase"></i> @break
                                @case('expense') <i class="fas fa-receipt"></i> @break
                                @case('social_case') <i class="fas fa-users"></i> @break
                                @case('task') <i class="fas fa-tasks"></i> @break
                                @case('custody_transfer') <i class="fas fa-exchange-alt"></i> @break
                                @case('purchase_request') <i class="fas fa-shopping-cart"></i> @break
                                @case('maintenance_request') <i class="fas fa-tools"></i> @break
                                @default <i class="fas fa-bell"></i>
                            @endswitch
                        </div>

                        <!-- Content -->
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                <div>
                                    <p class="mb-1" style="font-weight:{{ $notif->is_read ? '400' : '600' }};font-size:.95rem;">
                                        {{ $notif->title }}
                                        @if(!$notif->is_read)
                                            <span class="badge bg-primary ms-1" style="font-size:.65rem;">جديد</span>
                                        @endif
                                    </p>
                                    <p class="mb-0 text-muted" style="font-size:.85rem;">{{ $notif->message }}</p>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <div class="text-muted" style="font-size:.78rem;">
                                        <i class="fas fa-clock"></i>
                                        {{ $notif->created_at->diffForHumans() }}
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;margin-top:.15rem;">
                                        {{ $notif->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action -->
                        <div class="flex-shrink-0">
                            <form action="{{ route('notifications.read', $notif->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $notif->is_read ? 'btn-outline-secondary' : 'btn-primary' }}"
                                        title="{{ $notif->is_read ? 'عرض' : 'تعليم كمقروء والانتقال' }}">
                                    @if($notif->related_id)
                                        <i class="fas fa-arrow-left"></i>
                                    @else
                                        <i class="fas fa-check"></i>
                                    @endif
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-bell-slash fa-3x mb-3 d-block" style="opacity:.2;"></i>
                    لا توجد إشعارات بعد
                </div>
            </div>
            @endforelse

            <!-- Pagination -->
            @if($notifications->hasPages())
            <div class="d-flex justify-content-center mt-4">
                {{ $notifications->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
