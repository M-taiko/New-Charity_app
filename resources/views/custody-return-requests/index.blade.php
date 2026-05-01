@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-file-invoice-dollar"></i> طلبات رد العهدات
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        الموافقة على طلبات الرد للعهدات التي تخصك
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <i class="fas fa-hourglass-half text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">طلبات معلقة</div>
                            <h5 class="mb-0">{{ $stats['pending_count'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);">
                                <i class="fas fa-check-circle text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">معتمدة</div>
                            <h5 class="mb-0">{{ $stats['approved_count'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                                <i class="fas fa-times-circle text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">مرفوضة</div>
                            <h5 class="mb-0">{{ $stats['rejected_count'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-wallet text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">إجمالي المبالغ</div>
                            <h6 class="mb-0" style="font-size: 0.9rem;">{{ number_format($stats['total_pending_amount'], 2) }} ج.م</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Requests Table -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-clock"></i> الطلبات المعلقة
                    </h5>
                </div>
                <div class="card-body">
                    @if($pendingRequests->isEmpty())
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-check-circle"></i> لا توجد طلبات معلقة حالياً
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>رقم العهدة</th>
                                        <th>المندوب</th>
                                        <th>المحاسب (المطلب)</th>
                                        <th>المبلغ</th>
                                        <th>السبب</th>
                                        <th>التاريخ</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingRequests as $request)
                                    <tr>
                                        <td>{{ $request->id }}</td>
                                        <td>
                                            <a href="{{ route('custodies.show', $request->custody_id) }}" class="badge bg-primary">
                                                #{{ $request->custody_id }}
                                            </a>
                                        </td>
                                        <td>{{ $request->custody->agent->name }}</td>
                                        <td>{{ $request->requester->name }}</td>
                                        <td>
                                            <strong>{{ number_format($request->amount, 2) }} ج.م</strong>
                                        </td>
                                        <td>
                                            @if($request->reason)
                                                <small class="text-muted">{{ Str::limit($request->reason, 30) }}</small>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $request->created_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-success"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#approveModal{{ $request->id }}"
                                                        title="الموافقة">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#rejectModal{{ $request->id }}"
                                                        title="الرفض">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Approved Requests Table -->
    @if($approvedRequests->isNotEmpty())
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-check-circle"></i> الطلبات المعتمدة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>رقم العهدة</th>
                                    <th>المندوب</th>
                                    <th>المحاسب</th>
                                    <th>المبلغ</th>
                                    <th>المدير المعتمد</th>
                                    <th>تاريخ الموافقة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($approvedRequests as $request)
                                <tr>
                                    <td>{{ $request->id }}</td>
                                    <td>
                                        <a href="{{ route('custodies.show', $request->custody_id) }}" class="badge bg-primary">
                                            #{{ $request->custody_id }}
                                        </a>
                                    </td>
                                    <td>{{ $request->custody->agent->name }}</td>
                                    <td>{{ $request->requester->name }}</td>
                                    <td><strong>{{ number_format($request->amount, 2) }} ج.م</strong></td>
                                    <td>{{ $request->approver->name }}</td>
                                    <td>{{ $request->approved_at->format('Y-m-d H:i') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Rejected Requests Table -->
    @if($rejectedRequests->isNotEmpty())
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-times-circle"></i> الطلبات المرفوضة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>رقم العهدة</th>
                                    <th>المندوب</th>
                                    <th>المحاسب</th>
                                    <th>المبلغ</th>
                                    <th>سبب الرفض</th>
                                    <th>المدير</th>
                                    <th>تاريخ الرفض</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rejectedRequests as $request)
                                <tr>
                                    <td>{{ $request->id }}</td>
                                    <td>
                                        <a href="{{ route('custodies.show', $request->custody_id) }}" class="badge bg-primary">
                                            #{{ $request->custody_id }}
                                        </a>
                                    </td>
                                    <td>{{ $request->custody->agent->name }}</td>
                                    <td>{{ $request->requester->name }}</td>
                                    <td><strong>{{ number_format($request->amount, 2) }} ج.م</strong></td>
                                    <td>
                                        @if($request->approval_notes)
                                            <small class="text-muted">{{ Str::limit($request->approval_notes, 30) }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $request->approver->name }}</td>
                                    <td>{{ $request->approved_at->format('Y-m-d H:i') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Approve Modals -->
@foreach($pendingRequests as $request)
<div class="modal fade" id="approveModal{{ $request->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-check-circle"></i> الموافقة على الطلب</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custody-return-requests.approve', $request->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>العهدة #{{ $request->custody_id }}</strong><br>
                        المندوب: <strong>{{ $request->custody->agent->name }}</strong><br>
                        المبلغ: <strong>{{ number_format($request->amount, 2) }} ج.م</strong>
                    </div>
                    <p class="text-muted">هل تؤكد الموافقة على هذا الطلب؟ سيتم تنفيذ الرد مباشرة بعد الموافقة.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> الموافقة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<!-- Reject Modals -->
@foreach($pendingRequests as $request)
<div class="modal fade" id="rejectModal{{ $request->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-times-circle"></i> رفض الطلب</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custody-return-requests.reject', $request->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>العهدة #{{ $request->custody_id }}</strong><br>
                        المندوب: <strong>{{ $request->custody->agent->name }}</strong><br>
                        المبلغ: <strong>{{ number_format($request->amount, 2) }} ج.م</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">سبب الرفض <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="أدخل سبب الرفض..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> رفض الطلب
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
