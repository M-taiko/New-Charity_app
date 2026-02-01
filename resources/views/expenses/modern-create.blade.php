@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-receipt"></i> إضافة مصروف جديد
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-plus-circle"></i> بيانات المصروف
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('expenses.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label"><strong>اختر العهدة</strong></label>
                            <select name="custody_id" class="form-select @error('custody_id') is-invalid @enderror" required onchange="updateCustodyInfo()">
                                <option value="">-- اختر عهدة --</option>
                                @foreach($custodies as $custody)
                                    @php
                                        $actualSpent = $custody->getTotalSpent() - $custody->returned;
                                        $remaining = $custody->amount - $actualSpent;
                                    @endphp
                                    <option value="{{ $custody->id }}"
                                            data-remaining="{{ $remaining }}"
                                            {{ old('custody_id') == $custody->id ? 'selected' : '' }}>
                                        العهدة #{{ $custody->id }} - الوكيل: {{ $custody->agent->name }} (المتبقي: {{ number_format($remaining, 2) }} ر.س)
                                    </option>
                                @endforeach
                            </select>
                            @error('custody_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted" id="custody-info" style="display: none; margin-top: 0.5rem;">
                                المبلغ المتاح للصرف: <strong id="remaining-amount">0</strong> ر.س
                            </small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>نوع المصروف</strong></label>
                                <select name="type" class="form-select @error('type') is-invalid @enderror" required onchange="handleTypeChange()">
                                    <option value="">-- اختر نوعاً --</option>
                                    <option value="social_case" {{ old('type') == 'social_case' ? 'selected' : '' }}>حالة اجتماعية</option>
                                    <option value="general" {{ old('type') == 'general' ? 'selected' : '' }}>مصروف عام</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6" id="social-case-field" style="display: {{ old('type') == 'social_case' ? 'block' : 'none' }};">
                                <label class="form-label"><strong>الحالة الاجتماعية</strong></label>
                                <select name="social_case_id" class="form-select @error('social_case_id') is-invalid @enderror">
                                    <option value="">-- اختر حالة --</option>
                                    @foreach(\App\Models\SocialCase::all() as $case)
                                        <option value="{{ $case->id }}" {{ old('social_case_id') == $case->id ? 'selected' : '' }}>{{ $case->name }}</option>
                                    @endforeach
                                </select>
                                @error('social_case_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>المبلغ (ر.س)</strong></label>
                                <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" step="0.01" value="{{ old('amount') }}" required>
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>تاريخ المصروف</strong></label>
                                <input type="date" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror" value="{{ old('expense_date', date('Y-m-d')) }}" required>
                                @error('expense_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الوصف</strong></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" required>{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الموقع</strong></label>
                            <input type="text" name="location" class="form-control @error('location') is-invalid @enderror" value="{{ old('location') }}">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                                <i class="fas fa-save"></i> حفظ المصروف
                            </button>
                            <a href="{{ route('expenses.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); border: 1px solid rgba(245, 87, 108, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #f5576c;"></i> معلومات مهمة
                    </h6>
                    <ul style="font-size: 0.9rem; line-height: 1.8;">
                        <li>تأكد من صحة المبلغ والتاريخ</li>
                        <li>اختر الحالة الاجتماعية إذا كان المصروف متعلقاً بها</li>
                        <li>أضف وصفاً واضحاً للمصروف</li>
                        <li>يمكن تعديل بيانات المصروف لاحقاً</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function handleTypeChange() {
        const type = document.querySelector('select[name="type"]').value;
        const field = document.getElementById('social-case-field');
        if (type === 'social_case') {
            field.style.display = 'block';
        } else {
            field.style.display = 'none';
        }
    }

    function updateCustodyInfo() {
        const custodySelect = document.querySelector('select[name="custody_id"]');
        const selectedOption = custodySelect.options[custodySelect.selectedIndex];
        const infoElement = document.getElementById('custody-info');
        const amountElement = document.getElementById('remaining-amount');

        if (selectedOption.value) {
            const remaining = selectedOption.getAttribute('data-remaining');
            amountElement.textContent = parseFloat(remaining).toLocaleString('ar-SA', { minimumFractionDigits: 2 });
            infoElement.style.display = 'block';
        } else {
            infoElement.style.display = 'none';
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCustodyInfo();
    });
</script>
@endsection
