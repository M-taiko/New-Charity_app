@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-vault"></i> إدارة الخزائن
                    </h1>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#donationModal" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                        <i class="fas fa-arrow-down"></i> استقبال تبرع
                    </button>
                    <a href="{{ route('treasury.create') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                        <i class="fas fa-plus"></i> إضافة خزينة جديدة
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if($treasuries->isEmpty())
    <div class="row">
        <div class="col-12">
            <div class="card text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5>لا توجد خزائن حالياً</h5>
                <p class="text-muted">ابدأ بإضافة خزينة جديدة</p>
                <a href="{{ route('treasury.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> إضافة خزينة
                </a>
            </div>
        </div>
    </div>
    @else
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                <div class="card-body text-center">
                    <h6 style="color: #666; margin: 0;">إجمالي الأرصدة</h6>
                    <h3 style="color: #4caf50; margin: 10px 0 0 0;">{{ number_format($treasuries->sum('balance'), 2) }} ج.م</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(13, 71, 161, 0.1)); border: 1px solid rgba(33, 150, 243, 0.3);">
                <div class="card-body text-center">
                    <h6 style="color: #666; margin: 0;">عدد الخزائن</h6>
                    <h3 style="color: #2196f3; margin: 10px 0 0 0;">{{ $treasuries->count() }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(255, 111, 0, 0.1)); border: 1px solid rgba(255, 152, 0, 0.3);">
                <div class="card-body text-center">
                    <h6 style="color: #666; margin: 0;">متوسط الرصيد</h6>
                    <h3 style="color: #ff9800; margin: 10px 0 0 0;">{{ number_format($treasuries->avg('balance'), 2) }} ج.م</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Treasuries Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-list"></i> قائمة الخزائن
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>اسم الخزينة</th>
                                    <th>الرصيد الحالي</th>
                                    <th>عدد الحركات</th>
                                    <th>تاريخ الإنشاء</th>
                                    <th>الملاحظات</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($treasuries as $index => $treasury)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <strong style="font-size: 1.05rem;">{{ $treasury->name }}</strong>
                                    </td>
                                    <td>
                                        <strong style="color: #4caf50; font-size: 1.1rem;">
                                            {{ number_format($treasury->balance, 2) }} ج.م
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $treasury->transactions()->count() }}</span>
                                    </td>
                                    <td>{{ $treasury->created_at->format('Y-m-d') }}</td>
                                    <td>
                                        <small class="text-muted">{{ Str::limit($treasury->notes ?? '-', 30) }}</small>
                                    </td>
                                    <td>
                                        <a href="{{ route('treasury.show', $treasury) }}" class="btn btn-sm btn-info" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('treasury.edit', $treasury) }}" class="btn btn-sm btn-primary" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('treasury.destroy', $treasury) }}" method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer Option -->
    @if($treasuries->count() > 1)
    <div class="row mt-4">
        <div class="col-12">
            <div class="card" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 152, 0, 0.1)); border: 1px solid rgba(255, 152, 0, 0.3);">
                <div class="card-body">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h6 style="margin: 0; color: #333;">
                                <i class="fas fa-exchange-alt" style="color: #ff9800;"></i> تحويل أموال بين الخزائن
                            </h6>
                            <small class="text-muted">انقل الأموال من خزينة إلى أخرى</small>
                        </div>
                        <a href="{{ route('treasury.transfer') }}" class="btn btn-warning">
                            <i class="fas fa-exchange-alt"></i> تحويل
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endif
</div>

<!-- Donation Modal -->
<div class="modal fade" id="donationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-gift"></i> استقبال تبرع جديد</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="donationForm" method="POST" onsubmit="return submitDonationForm(event)">
                @csrf

                <div class="modal-body">
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle"></i> أدخل بيانات التبرع والخزينة المراد إضافة المبلغ إليها
                    </div>

                    <!-- Treasury Selection -->
                    <div class="mb-3">
                        <label class="form-label"><strong>الخزينة <span class="text-danger">*</span></strong></label>
                        <select name="treasury_id" id="donationTreasurySelect" class="form-select @error('treasury_id') is-invalid @enderror"
                                onchange="updateDonationTreasuryInfo()" required>
                            <option value="">-- اختر الخزينة --</option>
                            @forelse($treasuries ?? [] as $treasury)
                                <option value="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}">
                                    {{ $treasury->name }} (الرصيد الحالي: {{ number_format($treasury->balance, 2) }} ج.م)
                                </option>
                            @empty
                                <option disabled>لا توجد خزائن متاحة</option>
                            @endforelse
                        </select>
                        @error('treasury_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Amount Input -->
                    <div class="mb-3">
                        <label class="form-label"><strong>المبلغ (ج.م) <span class="text-danger">*</span></strong></label>
                        <input type="number" name="amount" id="donationAmount" class="form-control @error('amount') is-invalid @enderror"
                               step="0.01" min="0.01" value="{{ old('amount') }}"
                               placeholder="أدخل المبلغ" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Donation Source -->
                    <div class="mb-3">
                        <label class="form-label"><strong>جهة التبرع <span class="text-danger">*</span></strong></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" name="source" id="source_company" value="company" class="btn-check"
                                   {{ old('source', 'company') == 'company' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="source_company">
                                <i class="fas fa-building"></i> من الشركة
                            </label>

                            <input type="radio" name="source" id="source_external" value="external" class="btn-check"
                                   {{ old('source') == 'external' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="source_external">
                                <i class="fas fa-handshake"></i> من جهة خارجية
                            </label>

                            <input type="radio" name="source" id="source_returning" value="returnings" class="btn-check"
                                   {{ old('source') == 'returnings' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="source_returning">
                                <i class="fas fa-undo"></i> مردود/مرتجع
                            </label>
                        </div>
                        @error('source')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- External Source Name (conditional) -->
                    <div class="mb-3" id="externalSourceField" style="display: none;">
                        <label class="form-label"><strong>اسم الجهة الخارجية</strong></label>
                        <input type="text" name="external_source" id="externalSourceInput" class="form-control"
                               placeholder="مثال: جهة الأوقاف، متبرع خاص، إلخ" value="{{ old('external_source') }}">
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label"><strong>الملاحظات/الوصف</strong></label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                  rows="3" placeholder="أضف أي ملاحظات عن التبرع...">{{ old('description') }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Treasury Info Card -->
                    <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);"
                         id="treasuryInfoCard" style="display: none;">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">الرصيد الحالي:</p>
                                    <h5 id="currentBalance" style="margin: 5px 0 0; color: #4caf50;">0.00 ج.م</h5>
                                </div>
                                <div class="col-md-6">
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">الرصيد بعد الإضافة:</p>
                                    <h5 id="newBalance" style="margin: 5px 0 0; color: #2196f3;">0.00 ج.م</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> إضافة التبرع
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle external source field
document.querySelectorAll('input[name="source"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const externalField = document.getElementById('externalSourceField');
        if (this.value === 'external') {
            externalField.style.display = 'block';
            document.getElementById('externalSourceInput').focus();
        } else {
            externalField.style.display = 'none';
            document.getElementById('externalSourceInput').value = '';
        }
    });
});

function updateDonationTreasuryInfo() {
    const select = document.getElementById('donationTreasurySelect');
    const amountInput = document.getElementById('donationAmount');
    const infoCard = document.getElementById('treasuryInfoCard');
    const currentBalanceDisplay = document.getElementById('currentBalance');
    const newBalanceDisplay = document.getElementById('newBalance');

    if (select.value) {
        const option = select.options[select.selectedIndex];
        const currentBalance = parseFloat(option.dataset.balance) || 0;
        const amount = parseFloat(amountInput.value) || 0;
        const newBalance = currentBalance + amount;

        currentBalanceDisplay.textContent = currentBalance.toFixed(2) + ' ج.م';
        newBalanceDisplay.textContent = newBalance.toFixed(2) + ' ج.م';
        infoCard.style.display = 'block';
    } else {
        infoCard.style.display = 'none';
    }
}

// Update balances on amount change
document.getElementById('donationAmount').addEventListener('input', updateDonationTreasuryInfo);

function validateDonation() {
    const treasurySelect = document.getElementById('donationTreasurySelect');
    const amountInput = document.getElementById('donationAmount');
    const amount = parseFloat(amountInput.value) || 0;

    if (!treasurySelect.value) {
        alert('يرجى اختيار خزينة');
        return false;
    }

    if (amount <= 0) {
        alert('يرجى إدخال مبلغ صحيح أكبر من صفر');
        return false;
    }

    const sourceExternalInput = document.getElementById('externalSourceInput');
    const sourceExternal = document.getElementById('source_external').checked;

    if (sourceExternal && !sourceExternalInput.value.trim()) {
        alert('يرجى إدخال اسم الجهة الخارجية');
        sourceExternalInput.focus();
        return false;
    }

    return true;
}

function submitDonationForm(event) {
    event.preventDefault();

    if (!validateDonation()) {
        return false;
    }

    const treasuryId = document.getElementById('donationTreasurySelect').value;
    const form = document.getElementById('donationForm');

    // Dynamically set the form action with the selected treasury ID
    form.action = `/treasury/${treasuryId}/add-donation`;
    form.submit();
}

// Initialize external source field visibility on modal open
document.getElementById('donationModal').addEventListener('shown.bs.modal', function() {
    const sourceExternal = document.getElementById('source_external').checked;
    const externalField = document.getElementById('externalSourceField');
    externalField.style.display = sourceExternal ? 'block' : 'none';
    updateDonationTreasuryInfo();
});
</script>

@endsection
