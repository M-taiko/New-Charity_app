@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                    <i class="fas fa-eye"></i> تفاصيل التحويل
                </h1>
                <a href="{{ route('custody-transfers.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> العودة
                </a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert" data-aos="fade-down">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert" data-aos="fade-down">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12 col-lg-8" data-aos="fade-up">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-exchange-alt"></i> بيانات التحويل
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <p class="text-muted small mb-1">من (المندوب المرسل)</p>
                            <h6 class="mb-0" style="color: #667eea;">{{ $custodyTransfer->fromAgent->name }}</h6>
                        </div>
                        <div class="col-md-6 mb-4">
                            <p class="text-muted small mb-1">إلى (المندوب المستقبل)</p>
                            <h6 class="mb-0" style="color: #764ba2;">{{ $custodyTransfer->toAgent->name }}</h6>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <p class="text-muted small mb-1">المبلغ</p>
                            <h6 class="mb-0" style="color: #10b981;">{{ number_format($custodyTransfer->amount, 2) }} ج.م</h6>
                        </div>
                        <div class="col-md-6 mb-4">
                            <p class="text-muted small mb-1">الحالة</p>
                            @if ($custodyTransfer->status === 'pending')
                                <span class="badge bg-warning">قيد الانتظار</span>
                            @elseif ($custodyTransfer->status === 'approved')
                                <span class="badge bg-success">تم القبول</span>
                            @else
                                <span class="badge bg-danger">تم الرفض</span>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <p class="text-muted small mb-1">العهدة</p>
                            <h6 class="mb-0">عهدة #{{ $custodyTransfer->custody->id }}</h6>
                            <small class="text-muted">
                                المبلغ: {{ number_format($custodyTransfer->custody->amount, 2) }} ج.م
                            </small>
                        </div>
                        <div class="col-md-6 mb-4">
                            <p class="text-muted small mb-1">التاريخ</p>
                            <h6 class="mb-0">{{ $custodyTransfer->created_at->format('Y-m-d H:i') }}</h6>
                        </div>
                    </div>

                    @if ($custodyTransfer->notes)
                        <hr>
                        <div class="mb-4">
                            <p class="text-muted small mb-1">ملاحظات</p>
                            <p class="mb-0">{{ $custodyTransfer->notes }}</p>
                        </div>
                    @endif

                    @if ($custodyTransfer->rejection_reason)
                        <hr>
                        <div class="mb-4">
                            <p class="text-muted small mb-1">سبب الرفض</p>
                            <p class="mb-0" style="color: #ef4444;">{{ $custodyTransfer->rejection_reason }}</p>
                        </div>
                    @endif

                    @if ($custodyTransfer->approved_at)
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">تاريخ الموافقة</p>
                                <h6 class="mb-0">{{ $custodyTransfer->approved_at->format('Y-m-d H:i') }}</h6>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">من قبل</p>
                                <h6 class="mb-0">{{ $custodyTransfer->approver->name ?? '-' }}</h6>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions Panel -->
        <div class="col-12 col-lg-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-tasks"></i> الإجراءات
                    </h5>
                </div>
                <div class="card-body">
                    @if ($custodyTransfer->status === 'pending' && auth()->id() === $custodyTransfer->to_agent_id)
                        <p class="text-muted small mb-3">أنت المندوب المستقبل. اختر ما تريد:</p>

                        <form action="{{ route('custody-transfers.approve', $custodyTransfer) }}" method="POST" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check"></i> قبول التحويل
                            </button>
                        </form>

                        <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="fas fa-times"></i> رفض التحويل
                        </button>
                    @elseif ($custodyTransfer->status === 'pending')
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> في انتظار موافقة المندوب المستقبل
                        </div>
                    @elseif ($custodyTransfer->status === 'approved')
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> تم الموافقة على التحويل
                        </div>
                    @else
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i> تم رفض التحويل
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">
                    <i class="fas fa-ban"></i> رفض التحويل
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('custody-transfers.reject', $custodyTransfer) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">سبب الرفض</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="أدخل سبب الرفض..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-check"></i> تأكيد الرفض
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true
    });
</script>
@endpush

@endsection
