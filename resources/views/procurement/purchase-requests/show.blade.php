@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 style="margin:0;font-size:1.75rem;font-weight:700;">
                    <i class="fas fa-shopping-cart"></i> طلب شراء #{{ $purchaseRequest->id }}
                </h1>
                <span class="badge bg-{{ $purchaseRequest->status_color }} mt-1" style="font-size:.9rem;">
                    {{ $purchaseRequest->status_label }}
                </span>
            </div>
            <a href="{{ route('purchase-requests.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        <!-- Details -->
        <div class="col-lg-8" data-aos="fade-up">
            <div class="card">
                <div class="card-header"><h5 style="margin:0;"><i class="fas fa-info-circle"></i> تفاصيل الطلب</h5></div>
                <div class="card-body">
                    <table class="table table-borderless" style="font-size:.9rem;">
                        <tr><td class="text-muted" width="160">العنوان</td><td><strong>{{ $purchaseRequest->title }}</strong></td></tr>
                        <tr><td class="text-muted">الفئة</td><td><span class="badge bg-secondary">{{ $purchaseRequest->category_label }}</span></td></tr>
                        <tr><td class="text-muted">الأولوية</td><td><span class="badge bg-{{ $purchaseRequest->priority_color }}">{{ $purchaseRequest->priority_label }}</span></td></tr>
                        <tr><td class="text-muted">التكلفة التقديرية</td><td>{{ $purchaseRequest->estimated_cost ? number_format($purchaseRequest->estimated_cost, 2) . ' ج.م' : '—' }}</td></tr>
                        @if($purchaseRequest->actual_cost)
                        <tr><td class="text-muted">التكلفة الفعلية</td><td><strong style="color:var(--danger);">{{ number_format($purchaseRequest->actual_cost, 2) }} ج.م</strong></td></tr>
                        @endif
                        <tr><td class="text-muted">مطلوب بحلول</td><td>{{ $purchaseRequest->needed_by?->format('Y-m-d') ?? '—' }}</td></tr>
                        @if($purchaseRequest->supplier)
                        <tr><td class="text-muted">المورد</td><td>{{ $purchaseRequest->supplier->name }}</td></tr>
                        @endif
                        <tr><td class="text-muted">مقدم الطلب</td><td>{{ $purchaseRequest->requester->name }}</td></tr>
                        <tr><td class="text-muted">تاريخ الطلب</td><td>{{ $purchaseRequest->created_at->format('Y-m-d H:i') }}</td></tr>
                        @if($purchaseRequest->reviewer)
                        <tr><td class="text-muted">راجعه</td><td>{{ $purchaseRequest->reviewer->name }} — {{ $purchaseRequest->reviewed_at->format('Y-m-d') }}</td></tr>
                        @endif
                    </table>
                    @if($purchaseRequest->description)
                    <hr>
                    <h6 class="text-muted">التفاصيل</h6>
                    <p style="white-space:pre-line;line-height:1.7;">{{ $purchaseRequest->description }}</p>
                    @endif
                    @if($purchaseRequest->rejection_reason)
                    <div class="alert alert-danger mt-2"><i class="fas fa-times-circle"></i> <strong>سبب الرفض:</strong> {{ $purchaseRequest->rejection_reason }}</div>
                    @endif
                    @if($purchaseRequest->attachment)
                    <a href="{{ asset('storage/' . $purchaseRequest->attachment) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="fas fa-paperclip"></i> عرض المرفق
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
            @can('approve_custody')
            <div class="card mb-3">
                <div class="card-header"><h6 style="margin:0;"><i class="fas fa-cogs"></i> الإجراءات</h6></div>
                <div class="card-body d-flex flex-column gap-2">

                    @if($purchaseRequest->status === 'pending')
                    {{-- Approve --}}
                    <form action="{{ route('purchase-requests.approve', $purchaseRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-check"></i> الموافقة على الطلب
                        </button>
                    </form>

                    {{-- Reject --}}
                    <button class="btn btn-danger w-100" data-bs-toggle="collapse" data-bs-target="#rejectForm">
                        <i class="fas fa-times"></i> رفض الطلب
                    </button>
                    <div class="collapse" id="rejectForm">
                        <form action="{{ route('purchase-requests.reject', $purchaseRequest) }}" method="POST" class="mt-2">
                            @csrf
                            <textarea name="rejection_reason" class="form-control form-control-sm mb-2" rows="2" placeholder="سبب الرفض..." required></textarea>
                            <button type="submit" class="btn btn-danger btn-sm w-100">تأكيد الرفض</button>
                        </form>
                    </div>
                    @endif

                    @if($purchaseRequest->status === 'approved')
                    {{-- Mark Purchased --}}
                    <button class="btn btn-primary w-100" data-bs-toggle="collapse" data-bs-target="#purchasedForm">
                        <i class="fas fa-box"></i> تسجيل تنفيذ الشراء
                    </button>
                    <div class="collapse" id="purchasedForm">
                        <form action="{{ route('purchase-requests.purchased', $purchaseRequest) }}" method="POST" class="mt-2">
                            @csrf
                            <label class="form-label small">التكلفة الفعلية (ج.م)</label>
                            <input type="number" name="actual_cost" class="form-control form-control-sm mb-2"
                                   step="0.01" value="{{ $purchaseRequest->estimated_cost }}" min="0">
                            <button type="submit" class="btn btn-primary btn-sm w-100">تأكيد الشراء</button>
                        </form>
                    </div>
                    @endif

                </div>
            </div>
            @endcan

            @if($purchaseRequest->requested_by === auth()->id() && $purchaseRequest->status === 'pending')
            <form action="{{ route('purchase-requests.destroy', $purchaseRequest) }}" method="POST">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger w-100"
                        onclick="return confirm('حذف الطلب نهائياً؟')">
                    <i class="fas fa-trash"></i> حذف الطلب
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
