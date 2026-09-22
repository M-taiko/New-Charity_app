@extends('layouts.modern')

@section('content')
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h2><i class="fas fa-eye"></i> تفاصيل طلب التعديل</h2>
                    <p class="text-muted">المصروف #{{ $editRequest->expense_id }}</p>
                </div>
                <a href="{{ route('expense-edit-requests.index') }}" class="btn btn-secondary">
                    <i class="fas fa-list"></i> الطلبات
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- معلومات الطلب -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">معلومات الطلب</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>الطالب:</strong>
                            <p>{{ $editRequest->requester->name }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>التاريخ:</strong>
                            <p>{{ $editRequest->requested_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>الحالة:</strong>
                            <p>
                                @if($editRequest->isPending())
                                    <span class="badge bg-warning">معلق</span>
                                @elseif($editRequest->isApproved())
                                    <span class="badge bg-success">موافق</span>
                                @else
                                    <span class="badge bg-danger">مرفوض</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>المراجع:</strong>
                            <p>{{ $editRequest->reviewer?->name ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- مقارنة البيانات -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">مقارنة البيانات</h5>
                </div>
                <div class="card-body">
                    @php
                        $changedFields = $editRequest->getChangedFields();
                    @endphp

                    @if(empty($changedFields))
                        <p class="text-muted">لا توجد تغييرات</p>
                    @else
                        @foreach($changedFields as $field => $values)
                            <div class="mb-4">
                                <h6><strong>{{ \App\Models\ExpenseEditRequest::fieldLabel($field) }}</strong></h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="alert alert-danger mb-0">
                                            <small class="text-muted d-block">البيانات القديمة:</small>
                                            <code>{{ $values['old'] ?? '-' }}</code>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="alert alert-success mb-0">
                                            <small class="text-muted d-block">البيانات الجديدة:</small>
                                            <code>{{ $values['new'] ?? '-' }}</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- الإجراءات -->
            @if($editRequest->isPending())
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">الإجراءات</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- زر الموافقة -->
                            <div class="col-md-6 mb-3">
                                <form action="{{ route('expense-edit-requests.approve', $editRequest) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-block w-100">
                                        <i class="fas fa-check"></i> الموافقة على التعديل
                                    </button>
                                </form>
                            </div>

                            <!-- زر الرفض -->
                            <div class="col-md-6 mb-3">
                                <button type="button" class="btn btn-danger btn-block w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times"></i> رفض الطلب
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- رسالة الرفض (إن وجدت) -->
            @if($editRequest->isRejected() && $editRequest->rejection_reason)
                <div class="alert alert-danger">
                    <h6><strong>سبب الرفض:</strong></h6>
                    <p class="mb-0">{{ $editRequest->rejection_reason }}</p>
                </div>
            @endif
        </div>

        <!-- السايدبار -->
        <div class="col-md-4">
            <!-- بيانات المصروف الأصلية -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">بيانات المصروف الأصلية</h5>
                </div>
                <div class="card-body">
                    <p><strong>المعرف:</strong> #{{ $editRequest->expense_id }}</p>
                    <p><strong>المبلغ:</strong> {{ number_format($editRequest->expense->amount, 2) }} ج.م</p>
                    <p><strong>الوصف:</strong> {{ $editRequest->expense->description }}</p>
                    @if($editRequest->expense->location)
                        <p><strong>الموقع:</strong> {{ $editRequest->expense->location }}</p>
                    @endif
                    <p><strong>الفئة:</strong> {{ $editRequest->expense->category?->name ?? '-' }}</p>
                    <p><strong>التاريخ:</strong> {{ $editRequest->expense->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>

            <!-- معلومات الطلب -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">معلومات إضافية</h5>
                </div>
                <div class="card-body">
                    <p><strong>حالة الطلب:</strong></p>
                    <p>
                        @if($editRequest->isPending())
                            <span class="badge bg-warning">معلق - في انتظار الموافقة</span>
                        @elseif($editRequest->isApproved())
                            <span class="badge bg-success">تمت الموافقة - التغييرات مطبقة</span>
                        @else
                            <span class="badge bg-danger">تم الرفض</span>
                        @endif
                    </p>
                    @if($editRequest->reviewed_at)
                        <p><strong>تاريخ المراجعة:</strong> {{ $editRequest->reviewed_at->format('d/m/Y H:i') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نافذة الرفض -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">رفض طلب التعديل</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('expense-edit-requests.reject', $editRequest) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label for="rejection_reason" class="form-label">
                            <strong>السبب (مطلوب)</strong>
                        </label>
                        <textarea class="form-control @error('rejection_reason') is-invalid @enderror"
                                  id="rejection_reason" name="rejection_reason" rows="4"
                                  placeholder="اشرح سبب رفضك لطلب التعديل..." required></textarea>
                        @error('rejection_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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

<style>
code {
    display: block;
    padding: 8px;
    background-color: #f5f5f5;
    border-radius: 4px;
    word-break: break-all;
}

.alert code {
    background-color: transparent;
    padding: 0;
}
</style>
@endsection
