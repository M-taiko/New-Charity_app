@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-hand-holding-heart"></i>
                        إنشاء عهدة لمستخدم
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        اختر المستخدم وأدخل بيانات العهدة
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-plus-circle"></i> بيانات العهدة
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('custodies.store') }}" method="POST">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label"><strong>اختر المستخدم</strong></label>
                                <select name="agent_id" class="form-select @error('agent_id') is-invalid @enderror" required>
                                    <option value="">-- اختر مستخدماً --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('agent_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->phone ?? 'بدون رقم' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('agent_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>المبلغ (ج.م)</strong></label>
                                <input
                                    type="number"
                                    name="amount"
                                    id="custodyAmount"
                                    class="form-control @error('amount') is-invalid @enderror"
                                    step="0.01"
                                    value="{{ old('amount') }}"
                                    required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>تاريخ الإصدار</strong></label>
                                <input type="date" name="issued_date" class="form-control @error('issued_date') is-invalid @enderror" value="{{ old('issued_date', date('Y-m-d')) }}" required>
                                @error('issued_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الملاحظات</strong></label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4" placeholder="أضف أي ملاحظات إضافية...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-save"></i> إنشاء العهدة
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
            <div class="card mb-3" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(56, 142, 60, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-wallet" style="color: #4caf50;"></i> رصيد الخزينة
                    </h6>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #4caf50;">
                        {{ number_format($treasury->balance, 2) }} ج.م
                    </div>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.85rem;">
                        الرصيد المتاح في الخزينة
                    </p>
                </div>
            </div>

            <div class="card" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 1px solid rgba(102, 126, 234, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #667eea;"></i> معلومات مهمة
                    </h6>
                    <ul style="font-size: 0.9rem; line-height: 1.8;">
                        <li>اختر المستخدم الذي تريد إنشاء العهدة له</li>
                        <li>تأكد من أن المبلغ صحيح</li>
                        <li>سيتم إرسال إشعار للمستخدم للموافقة على العهدة</li>
                        <li>عند قبول المستخدم، سيتم صرف الفلوس مباشرة من الخزينة</li>
                        <li>احتفظ بالملاحظات واضحة ومختصرة</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
