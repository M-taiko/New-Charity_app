@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>تفاصيل الحالة الاجتماعية</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>اسم الحالة:</strong></label>
                            <p>{{ $socialCase->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>رقم الهوية:</strong></label>
                            <p>{{ $socialCase->national_id ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>رقم الهاتف:</strong></label>
                            <p>{{ $socialCase->phone ?? '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>الباحث:</strong></label>
                            <p>{{ $socialCase->researcher->name }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>نوع المساعدة:</strong></label>
                            <p>{{ $socialCase->assistance_type }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>الحالة:</strong></label>
                            <p>
                                @switch($socialCase->status)
                                    @case('pending')
                                        <span class="badge bg-warning">قيد الانتظار</span>
                                        @break
                                    @case('approved')
                                        <span class="badge bg-success">موافق عليه</span>
                                        @break
                                    @case('rejected')
                                        <span class="badge bg-danger">مرفوض</span>
                                        @break
                                    @case('completed')
                                        <span class="badge bg-secondary">مكتمل</span>
                                        @break
                                @endswitch
                            </p>
                        </div>
                    </div>

                    @if($socialCase->description)
                    <div class="mt-3">
                        <label class="form-label"><strong>الوصف:</strong></label>
                        <p>{{ $socialCase->description }}</p>
                    </div>
                    @endif

                    @if($socialCase->getTotalSpent() > 0)
                    <div class="mt-3 alert alert-info">
                        <strong>المبلغ المصروف:</strong> {{ number_format($socialCase->getTotalSpent(), 2) }} ج.م
                    </div>
                    @endif

                    <hr>

                    <div class="d-flex gap-2">
                        <a href="{{ route('social_cases.index') }}" class="btn btn-secondary">رجوع</a>
                        @can('review_social_case')
                            @if($socialCase->status == 'pending')
                                <form action="{{ route('social_cases.approve', $socialCase->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success">الموافقة</button>
                                </form>
                                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">الرفض</button>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>

            <!-- Reject Modal -->
            <div class="modal fade" id="rejectModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">رفض الحالة</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="{{ route('social_cases.reject', $socialCase->id) }}" method="POST">
                            @csrf
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">ملاحظات الرفض</label>
                                    <textarea name="notes" class="form-control"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-danger">رفض</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
