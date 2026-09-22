@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-history"></i> سجل النشاط
            </h1>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-3">
                    <form method="GET" action="{{ route('activity-logs.index') }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-sm-6 col-md-3">
                                <label class="form-label small mb-1"><i class="fas fa-user"></i> المستخدم</label>
                                <input type="text" name="user_filter" class="form-control form-control-sm"
                                       placeholder="ابحث بالاسم..." value="{{ request('user_filter') }}">
                            </div>
                            <div class="col-12 col-sm-6 col-md-2">
                                <label class="form-label small mb-1"><i class="fas fa-tag"></i> نوع الحدث</label>
                                <select name="event_filter" class="form-select form-select-sm">
                                    <option value="">الكل</option>
                                    @foreach($events as $event)
                                        <option value="{{ $event }}" {{ request('event_filter') === $event ? 'selected' : '' }}>
                                            {{ match($event) {
                                                'created'   => 'إنشاء',
                                                'updated'   => 'تعديل',
                                                'deleted'   => 'حذف',
                                                'approved'  => 'موافقة',
                                                'rejected'  => 'رفض',
                                                'returned'  => 'رد',
                                                'reviewed'  => 'مراجعة',
                                                'login'     => 'تسجيل دخول',
                                                'completed' => 'إتمام',
                                                'assigned'  => 'تكليف',
                                                default     => $event
                                            } }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-sm-6 col-md-2">
                                <label class="form-label small mb-1"><i class="fas fa-calendar"></i> من تاريخ</label>
                                <input type="date" name="date_from" class="form-control form-control-sm"
                                       value="{{ request('date_from') }}">
                            </div>
                            <div class="col-12 col-sm-6 col-md-2">
                                <label class="form-label small mb-1"><i class="fas fa-calendar"></i> إلى تاريخ</label>
                                <input type="date" name="date_to" class="form-control form-control-sm"
                                       value="{{ request('date_to') }}">
                            </div>
                            <div class="col-12 col-sm-6 col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                    <i class="fas fa-search"></i> بحث
                                </button>
                                <a href="{{ route('activity-logs.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                                    <i class="fas fa-times"></i> إعادة
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Table -->
    <div class="row" data-aos="fade-up" data-aos-delay="100">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 style="margin: 0;"><i class="fas fa-list"></i> السجلات</h5>
                    <small class="text-muted">{{ $logs->total() }} سجل</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 130px;">الوقت</th>
                                    <th style="width: 150px;">المستخدم</th>
                                    <th style="width: 100px;">الحدث</th>
                                    <th style="width: 100px;">النوع</th>
                                    <th>الوصف</th>
                                    <th style="width: 100px;">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($logs as $log)
                                <tr>
                                    <td style="font-size: 0.8rem; white-space: nowrap;">
                                        <i class="fas fa-clock text-muted"></i>
                                        {{ $log->created_at->format('Y-m-d') }}<br>
                                        <span class="text-muted">{{ $log->created_at->format('H:i:s') }}</span>
                                    </td>
                                    <td>
                                        @if($log->user)
                                            <div style="font-size: 0.875rem; font-weight: 600;">{{ $log->user->name }}</div>
                                            <small class="text-muted">{{ $log->user->getRoleNames()->first() }}</small>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $log->event_color }}">
                                            {{ $log->event_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.8rem; color: #6b7280;">{{ $log->subject_label }}</span>
                                        @if($log->subject_id)
                                            <br><code style="font-size: 0.75rem;">#{{ $log->subject_id }}</code>
                                        @endif
                                    </td>
                                    <td style="font-size: 0.875rem;">{{ $log->description }}</td>
                                    <td style="font-size: 0.75rem; color: #9ca3af; font-family: monospace;">
                                        {{ $log->ip_address ?? '—' }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-history" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                                        لا توجد سجلات نشاط
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($logs->hasPages())
                <div class="card-footer d-flex justify-content-center">
                    {{ $logs->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
