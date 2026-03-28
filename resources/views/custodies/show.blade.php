@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>تفاصيل العهدة</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>المندوب:</strong></label>
                            <p>{{ $custody->agent->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>المحاسب:</strong></label>
                            <p>{{ $custody->accountant->name }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>المبلغ الكلي:</strong></label>
                            <p class="text-primary">{{ number_format($custody->amount, 2) }} ج.م</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>الحالة:</strong></label>
                            <p>
                                @switch($custody->status)
                                    @case('pending')
                                        <span class="badge bg-warning">قيد الانتظار</span>
                                        @break
                                    @case('accepted')
                                        <span class="badge bg-success">مقبول</span>
                                        @break
                                    @case('rejected')
                                        <span class="badge bg-danger">مرفوض</span>
                                        @break
                                    @case('partially_returned')
                                        <span class="badge bg-info">مرتجع جزئياً</span>
                                        @break
                                    @case('closed')
                                        <span class="badge bg-secondary">مغلق</span>
                                        @break
                                @endswitch
                            </p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label"><strong>المصروف:</strong></label>
                            <p class="text-danger">{{ number_format($custody->spent, 2) }} ج.م</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>المرجع:</strong></label>
                            <p>{{ number_format($custody->returned, 2) }} ج.م</p>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><strong>المتبقي:</strong></label>
                            <p class="text-success">{{ number_format($custody->getRemainingBalance(), 2) }} ج.م</p>
                        </div>
                    </div>

                    <div class="progress mb-3">
                        <div class="progress-bar" role="progressbar" style="width: {{ ($custody->spent / $custody->amount) * 100 }}%"></div>
                    </div>
                    <small class="text-muted">نسبة الإنفاق: {{ round(($custody->spent / $custody->amount) * 100) }}%</small>

                    @if($custody->notes)
                    <div class="mt-3">
                        <label class="form-label"><strong>الملاحظات:</strong></label>
                        <p>{{ $custody->notes }}</p>
                    </div>
                    @endif

                    <div class="mt-3">
                        <label class="form-label"><strong>التاريخ:</strong></label>
                        <p>{{ $custody->created_at->format('Y-m-d H:i') }}</p>
                    </div>

                    @if($custody->accepted_at)
                    <div class="mt-2">
                        <label class="form-label"><strong>تاريخ القبول:</strong></label>
                        <p>{{ $custody->accepted_at->format('Y-m-d H:i') }}</p>
                    </div>
                    @endif

                    <hr>

                    <div class="d-flex gap-2">
                        <a href="{{ route('custodies.index') }}" class="btn btn-secondary">رجوع</a>
                        @if($custody->status == 'pending')
                            <form action="{{ route('custodies.accept', $custody->id) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-success">قبول العهدة</button>
                            </form>
                        @elseif($custody->status == 'accepted')
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#returnModal">إرجاع العهدة</button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Return Modal -->
            <div class="modal fade" id="returnModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">إرجاع العهدة</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="{{ route('custodies.return', $custody->id) }}" method="POST">
                            @csrf
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">المبلغ المرجع (ج.م)</label>
                                    <input type="number" step="0.01" name="returned_amount" class="form-control" max="{{ $custody->getRemainingBalance() }}" required>
                                    <small class="text-muted">الحد الأقصى: {{ number_format($custody->getRemainingBalance(), 2) }} ج.م</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-primary">إرجاع</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
