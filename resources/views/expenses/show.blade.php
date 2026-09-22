@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>تفاصيل المصروف</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>المستخدم:</strong></label>
                            <p>{{ $expense->user->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>النوع:</strong></label>
                            <p>{{ $expense->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>المبلغ:</strong></label>
                            <p class="text-danger">{{ number_format($expense->amount, 2) }} ج.م</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>التاريخ:</strong></label>
                            <p>{{ $expense->expense_date->format('Y-m-d') }}</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>الوصف:</strong></label>
                        <p>{{ $expense->description }}</p>
                    </div>

                    @if($expense->location)
                    <div class="mb-3">
                        <label class="form-label"><strong>الموقع:</strong></label>
                        <p>{{ $expense->location }}</p>
                    </div>
                    @endif

                    @if($expense->socialCase)
                    <div class="mb-3">
                        <label class="form-label"><strong>الحالة الاجتماعية:</strong></label>
                        <p>{{ $expense->socialCase->name }}</p>
                    </div>
                    @endif

                    <!-- Approval Status Section -->
                    <hr class="my-4">
                    <h6 class="mb-3"><i class="fas fa-check-circle"></i> <strong>حالة الاعتماد</strong></h6>

                    @if($expense->reviewed_at && $expense->reviewed_by_id)
                        <div class="alert alert-success" role="alert">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas fa-check-circle fa-lg" style="color: #28a745;"></i>
                                <strong>تم اعتماد المصروف</strong>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label"><strong>اعتمده:</strong></label>
                                    <p class="fw-bold">
                                        {{ $expense->reviewed_by_user?->name ?? 'غير معروف' }}
                                        @if($expense->reviewed_by_user)
                                            <span class="badge bg-info">
                                                @if($expense->reviewed_by_user->hasRole('مدير'))
                                                    مدير
                                                @elseif($expense->reviewed_by_user->hasRole('محاسب'))
                                                    محاسب
                                                @endif
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><strong>تاريخ الاعتماد:</strong></label>
                                    <p>{{ $expense->reviewed_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning" role="alert">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-clock fa-lg" style="color: #ffc107;"></i>
                                <strong>المصروف قيد المراجعة</strong>
                            </div>
                            <small class="d-block mt-2 text-muted">لم يتم اعتماد هذا المصروف من قبل المدير أو المحاسب بعد</small>
                        </div>
                    @endif

                    <div class="d-flex gap-2 mt-4">
                        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">رجوع</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
