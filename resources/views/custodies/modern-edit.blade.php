@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-edit"></i> تعديل العهدة
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-pen-square"></i> بيانات العهدة
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('custodies.update', $custody->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label"><strong>المبلغ (ر.س)</strong></label>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" step="0.01" value="{{ old('amount', $custody->amount) }}" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الملاحظات</strong></label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4">{{ old('notes', $custody->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-save"></i> حفظ التعديلات
                            </button>
                            <a href="{{ route('custodies.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 1px solid rgba(102, 126, 234, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #667eea;"></i> معلومات العهدة
                    </h6>
                    <div style="font-size: 0.9rem; line-height: 1.8;">
                        <div class="mb-2">
                            <strong>الحالة:</strong>
                            @switch($custody->status)
                                @case('pending')
                                    <span class="badge bg-warning">قيد الانتظار</span>
                                    @break
                                @case('accepted')
                                    <span class="badge bg-success">موافق عليه</span>
                                    @break
                                @case('rejected')
                                    <span class="badge bg-danger">مرفوض</span>
                                    @break
                            @endswitch
                        </div>
                        <div class="mb-2">
                            <strong>المبلغ المصروف:</strong> {{ number_format($custody->getTotalSpent(), 2) }} ر.س
                        </div>
                        <div class="mb-2">
                            <strong>المبلغ المتبقي:</strong> {{ number_format($custody->amount - $custody->getTotalSpent(), 2) }} ر.س
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
