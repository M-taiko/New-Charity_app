@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 style="margin:0;font-size:1.75rem;font-weight:700;">
                    <i class="fas fa-tools"></i> طلب صيانة #{{ $maintenanceRequest->id }}
                </h1>
                <span class="badge bg-{{ $maintenanceRequest->status_color }} mt-1" style="font-size:.9rem;">
                    {{ $maintenanceRequest->status_label }}
                </span>
            </div>
            <a href="{{ route('maintenance-requests.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8" data-aos="fade-up">
            <div class="card">
                <div class="card-header"><h5 style="margin:0;"><i class="fas fa-info-circle"></i> تفاصيل المشكلة</h5></div>
                <div class="card-body">
                    <table class="table table-borderless" style="font-size:.9rem;">
                        <tr><td class="text-muted" width="150">المشكلة</td><td><strong>{{ $maintenanceRequest->title }}</strong></td></tr>
                        <tr><td class="text-muted">الموقع</td><td>{{ $maintenanceRequest->location ?? '—' }}</td></tr>
                        <tr><td class="text-muted">الأولوية</td><td><span class="badge bg-{{ $maintenanceRequest->priority_color }}">{{ $maintenanceRequest->priority_label }}</span></td></tr>
                        <tr><td class="text-muted">المُبلِّغ</td><td>{{ $maintenanceRequest->reporter->name }}</td></tr>
                        <tr><td class="text-muted">تاريخ الإبلاغ</td><td>{{ $maintenanceRequest->created_at->format('Y-m-d H:i') }}</td></tr>
                        @if($maintenanceRequest->assignee)
                        <tr><td class="text-muted">المكلَّف بالإصلاح</td><td><strong>{{ $maintenanceRequest->assignee->name }}</strong></td></tr>
                        @endif
                        @if($maintenanceRequest->resolved_at)
                        <tr><td class="text-muted">تاريخ الحل</td><td>{{ $maintenanceRequest->resolved_at->format('Y-m-d H:i') }}</td></tr>
                        @endif
                    </table>
                    @if($maintenanceRequest->description)
                    <hr>
                    <h6 class="text-muted">وصف المشكلة</h6>
                    <p style="white-space:pre-line;line-height:1.7;">{{ $maintenanceRequest->description }}</p>
                    @endif
                    @if($maintenanceRequest->resolution_notes)
                    <div class="alert alert-success mt-2">
                        <i class="fas fa-check-circle"></i> <strong>ملاحظات الحل:</strong>
                        <p class="mb-0 mt-1" style="white-space:pre-line;">{{ $maintenanceRequest->resolution_notes }}</p>
                    </div>
                    @endif
                    @if($maintenanceRequest->attachment)
                    <a href="{{ asset('storage/' . $maintenanceRequest->attachment) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="fas fa-paperclip"></i> عرض المرفق
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
            @can('approve_custody')
            @if($maintenanceRequest->status === 'pending')
            <div class="card mb-3">
                <div class="card-header"><h6 style="margin:0;"><i class="fas fa-user-cog"></i> تكليف موظف</h6></div>
                <div class="card-body">
                    <form action="{{ route('maintenance-requests.assign', $maintenanceRequest) }}" method="POST">
                        @csrf
                        <select name="assigned_to" class="form-select form-select-sm mb-2" required>
                            <option value="">اختر الموظف المسؤول...</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-info btn-sm w-100">
                            <i class="fas fa-user-check"></i> تكليف وبدء المعالجة
                        </button>
                    </form>
                </div>
            </div>

            <form action="{{ route('maintenance-requests.reject', $maintenanceRequest) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-danger w-100 mb-3"
                        onclick="return confirm('رفض الطلب؟')">
                    <i class="fas fa-times"></i> رفض الطلب
                </button>
            </form>
            @endif
            @endcan

            @if(
                in_array($maintenanceRequest->status, ['pending','in_progress']) &&
                ($maintenanceRequest->assigned_to === auth()->id() ||
                 auth()->user()->hasRole('مدير') ||
                 auth()->user()->hasRole('محاسب'))
            )
            <div class="card">
                <div class="card-header"><h6 style="margin:0;"><i class="fas fa-check-double"></i> تسجيل الحل</h6></div>
                <div class="card-body">
                    <form action="{{ route('maintenance-requests.resolve', $maintenanceRequest) }}" method="POST">
                        @csrf
                        <textarea name="resolution_notes" class="form-control form-control-sm mb-2" rows="3"
                                  placeholder="ما الذي تم إصلاحه؟ (اختياري)"></textarea>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check"></i> تم الإصلاح
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
