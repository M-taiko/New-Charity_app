@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-exchange-alt"></i> تحويل أموال بين الخزائن
                    </h1>
                </div>
                <div>
                    <a href="{{ route('treasury.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> عودة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #ff9800 0%, #f44336 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-exchange-alt"></i> نموذج التحويل
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('treasury.perform-transfer') }}" method="POST" onsubmit="return validateTransfer()">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="from_treasury_id" class="form-label">الخزينة المصدرة <span class="text-danger">*</span></label>
                                    <select class="form-select @error('from_treasury_id') is-invalid @enderror"
                                            id="from_treasury_id" name="from_treasury_id" required onchange="updateTreasuryBalance()">
                                        <option value="">اختر الخزينة المصدرة...</option>
                                        @foreach($treasuries as $treasury)
                                            <option value="{{ $treasury->id }}"
                                                    data-balance="{{ $treasury->balance }}"
                                                    {{ old('from_treasury_id') == $treasury->id ? 'selected' : '' }}>
                                                {{ $treasury->name }} (رصيد: {{ number_format($treasury->balance, 2) }} ج.م)
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('from_treasury_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="card" style="background: linear-gradient(135deg, rgba(244, 67, 54, 0.1), rgba(211, 47, 47, 0.1)); border: 1px solid rgba(244, 67, 54, 0.3);">
                                    <div class="card-body text-center">
                                        <h6 style="color: #666; margin: 0;">الرصيد المتاح</h6>
                                        <h3 id="fromBalance" style="color: #f44336; margin: 10px 0 0 0;">0.00 ج.م</h3>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="to_treasury_id" class="form-label">الخزينة المستقبلة <span class="text-danger">*</span></label>
                                    <select class="form-select @error('to_treasury_id') is-invalid @enderror"
                                            id="to_treasury_id" name="to_treasury_id" required onchange="updateToTreasuryBalance()">
                                        <option value="">اختر الخزينة المستقبلة...</option>
                                        @foreach($treasuries as $treasury)
                                            <option value="{{ $treasury->id }}"
                                                    data-balance="{{ $treasury->balance }}"
                                                    {{ old('to_treasury_id') == $treasury->id ? 'selected' : '' }}>
                                                {{ $treasury->name }} (رصيد: {{ number_format($treasury->balance, 2) }} ج.م)
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('to_treasury_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                                    <div class="card-body text-center">
                                        <h6 style="color: #666; margin: 0;">الرصيد الحالي</h6>
                                        <h3 id="toBalance" style="color: #4caf50; margin: 10px 0 0 0;">0.00 ج.م</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3 mt-4">
                            <label for="amount" class="form-label">المبلغ المراد تحويله <span class="text-danger">*</span></label>
                            <input type="number" class="form-control @error('amount') is-invalid @enderror"
                                   id="amount" name="amount" step="0.01" min="0.01"
                                   value="{{ old('amount') }}"
                                   placeholder="أدخل المبلغ" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">الحد الأدنى: 0.01 ج.م</small>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">ملاحظات التحويل <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description" name="description" rows="3"
                                      placeholder="أضف ملاحظات عن سبب التحويل"
                                      required>{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Preview Cards -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card" style="background: linear-gradient(135deg, rgba(244, 67, 54, 0.15), rgba(211, 47, 47, 0.15)); border: 2px solid rgba(244, 67, 54, 0.3);">
                                    <div class="card-body text-center">
                                        <h6 style="color: #666; margin: 0;">رصيد الخزينة المصدرة بعد التحويل</h6>
                                        <h3 id="fromPreview" style="color: #f44336; margin: 10px 0 0 0;">0.00 ج.م</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.15), rgba(139, 195, 74, 0.15)); border: 2px solid rgba(76, 175, 80, 0.3);">
                                    <div class="card-body text-center">
                                        <h6 style="color: #666; margin: 0;">رصيد الخزينة المستقبلة بعد التحويل</h6>
                                        <h3 id="toPreview" style="color: #4caf50; margin: 10px 0 0 0;">0.00 ج.م</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-check-circle"></i> تحويل الأموال
                            </button>
                            <a href="{{ route('treasury.index') }}" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateTreasuryBalance() {
    const select = document.getElementById('from_treasury_id');
    const option = select.options[select.selectedIndex];
    const balance = option.dataset.balance || 0;
    document.getElementById('fromBalance').textContent = parseFloat(balance).toFixed(2) + ' ج.م';
    updatePreview();
}

function updateToTreasuryBalance() {
    const select = document.getElementById('to_treasury_id');
    const option = select.options[select.selectedIndex];
    const balance = option.dataset.balance || 0;
    document.getElementById('toBalance').textContent = parseFloat(balance).toFixed(2) + ' ج.م';
    updatePreview();
}

function updatePreview() {
    const fromSelect = document.getElementById('from_treasury_id');
    const toSelect = document.getElementById('to_treasury_id');
    const amountInput = document.getElementById('amount');

    const fromBalance = parseFloat(fromSelect.options[fromSelect.selectedIndex].dataset.balance || 0);
    const toBalance = parseFloat(toSelect.options[toSelect.selectedIndex].dataset.balance || 0);
    const amount = parseFloat(amountInput.value || 0);

    const fromPreview = fromBalance - amount;
    const toPreview = toBalance + amount;

    document.getElementById('fromPreview').textContent = fromPreview.toFixed(2) + ' ج.م';
    document.getElementById('toPreview').textContent = toPreview.toFixed(2) + ' ج.م';

    // Change color if balance goes negative
    const fromPreviewCard = document.getElementById('fromPreview').closest('.card');
    if (fromPreview < 0) {
        fromPreviewCard.style.borderColor = '#f44336';
        fromPreviewCard.style.borderWidth = '2px';
        document.getElementById('fromPreview').style.color = '#d32f2f';
    } else {
        fromPreviewCard.style.borderColor = 'rgba(244, 67, 54, 0.3)';
        document.getElementById('fromPreview').style.color = '#f44336';
    }
}

function validateTransfer() {
    const fromId = document.getElementById('from_treasury_id').value;
    const toId = document.getElementById('to_treasury_id').value;
    const amount = parseFloat(document.getElementById('amount').value || 0);
    const fromSelect = document.getElementById('from_treasury_id');
    const fromBalance = parseFloat(fromSelect.options[fromSelect.selectedIndex].dataset.balance || 0);

    if (!fromId || !toId) {
        alert('يجب اختيار خزينتين مختلفتين');
        return false;
    }

    if (fromId === toId) {
        alert('يجب اختيار خزينتين مختلفتين');
        return false;
    }

    if (amount <= 0) {
        alert('يجب إدخال مبلغ أكبر من صفر');
        return false;
    }

    if (amount > fromBalance) {
        alert('الرصيد المتاح غير كافي. الرصيد المتاح: ' + fromBalance.toFixed(2) + ' ج.م');
        return false;
    }

    return true;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateTreasuryBalance();
    updateToTreasuryBalance();

    document.getElementById('amount').addEventListener('input', updatePreview);
});
</script>

@endsection
