@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h1 style="margin:0;font-size:1.75rem;font-weight:700;">
                    <i class="fas fa-shopping-cart"></i> طلب شراء #{{ $purchaseRequest->id }}
                </h1>
                <span class="badge bg-{{ $purchaseRequest->status_color }} mt-1" style="font-size:.9rem;">
                    {{ $purchaseRequest->status_label }}
                </span>
            </div>
            <a href="{{ route('purchase-requests.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        <!-- Details -->
        <div class="col-lg-8" data-aos="fade-up">
            <div class="card">
                <div class="card-header"><h5 style="margin:0;"><i class="fas fa-info-circle"></i> تفاصيل الطلب</h5></div>
                <div class="card-body">
                    <table class="table table-borderless" style="font-size:.9rem;">
                        <tr><td class="text-muted" width="160">العنوان</td><td><strong>{{ $purchaseRequest->title }}</strong></td></tr>
                        <tr><td class="text-muted">الفئة</td><td><span class="badge bg-secondary">{{ $purchaseRequest->category_label }}</span></td></tr>
                        <tr><td class="text-muted">الأولوية</td><td><span class="badge bg-{{ $purchaseRequest->priority_color }}">{{ $purchaseRequest->priority_label }}</span></td></tr>
                        <tr><td class="text-muted">التكلفة التقديرية</td><td>{{ $purchaseRequest->estimated_cost ? number_format($purchaseRequest->estimated_cost, 2) . ' ج.م' : '—' }}</td></tr>
                        @if($purchaseRequest->actual_cost)
                        <tr><td class="text-muted">التكلفة الفعلية</td><td><strong style="color:var(--danger);">{{ number_format($purchaseRequest->actual_cost, 2) }} ج.م</strong></td></tr>
                        @endif
                        <tr><td class="text-muted">مطلوب بحلول</td><td>{{ $purchaseRequest->needed_by?->format('Y-m-d') ?? '—' }}</td></tr>
                        @if($purchaseRequest->supplier)
                        <tr><td class="text-muted">المورد</td><td>{{ $purchaseRequest->supplier->name }}</td></tr>
                        @endif
                        <tr><td class="text-muted">مقدم الطلب</td><td>{{ $purchaseRequest->requester->name }}</td></tr>
                        <tr><td class="text-muted">تاريخ الطلب</td><td>{{ $purchaseRequest->created_at->format('Y-m-d H:i') }}</td></tr>
                        @if($purchaseRequest->reviewer)
                        <tr><td class="text-muted">راجعه</td><td>{{ $purchaseRequest->reviewer->name }} — {{ $purchaseRequest->reviewed_at->format('Y-m-d') }}</td></tr>
                        @endif
                    </table>
                    @if($purchaseRequest->description)
                    <hr>
                    <h6 class="text-muted">التفاصيل</h6>
                    <p style="white-space:pre-line;line-height:1.7;">{{ $purchaseRequest->description }}</p>
                    @endif
                    @if($purchaseRequest->rejection_reason)
                    <div class="alert alert-danger mt-2"><i class="fas fa-times-circle"></i> <strong>سبب الرفض:</strong> {{ $purchaseRequest->rejection_reason }}</div>
                    @endif
                    @if($purchaseRequest->attachment)
                    <a href="{{ asset('storage/' . $purchaseRequest->attachment) }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="fas fa-paperclip"></i> عرض المرفق
                    </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
            @can('approve_custody')
            <div class="card mb-3">
                <div class="card-header"><h6 style="margin:0;"><i class="fas fa-cogs"></i> الإجراءات</h6></div>
                <div class="card-body d-flex flex-column gap-2">

                    @if($purchaseRequest->status === 'pending')
                    {{-- Approve with modal --}}
                    <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#approveModal">
                        <i class="fas fa-check"></i> الموافقة على الطلب
                    </button>

                    {{-- Reject --}}
                    <button class="btn btn-danger w-100" data-bs-toggle="collapse" data-bs-target="#rejectForm">
                        <i class="fas fa-times"></i> رفض الطلب
                    </button>
                    <div class="collapse" id="rejectForm">
                        <form action="{{ route('purchase-requests.reject', $purchaseRequest) }}" method="POST" class="mt-2">
                            @csrf
                            <textarea name="rejection_reason" class="form-control form-control-sm mb-2" rows="2" placeholder="سبب الرفض..." required></textarea>
                            <button type="submit" class="btn btn-danger btn-sm w-100">تأكيد الرفض</button>
                        </form>
                    </div>
                    @endif

                    @if($purchaseRequest->status === 'approved')
                    {{-- Mark Purchased --}}
                    <button class="btn btn-primary w-100" data-bs-toggle="collapse" data-bs-target="#purchasedForm">
                        <i class="fas fa-box"></i> تسجيل تنفيذ الشراء
                    </button>
                    <div class="collapse" id="purchasedForm">
                        <form action="{{ route('purchase-requests.purchased', $purchaseRequest) }}" method="POST" class="mt-2">
                            @csrf
                            <label class="form-label small">التكلفة الفعلية (ج.م)</label>
                            <input type="number" name="actual_cost" class="form-control form-control-sm mb-2"
                                   step="0.01" value="{{ $purchaseRequest->estimated_cost }}" min="0">
                            <button type="submit" class="btn btn-primary btn-sm w-100">تأكيد الشراء</button>
                        </form>
                    </div>
                    @endif

                </div>
            </div>
            @endcan

            @if($purchaseRequest->requested_by === auth()->id() && $purchaseRequest->status === 'pending')
            <form action="{{ route('purchase-requests.destroy', $purchaseRequest) }}" method="POST">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline-danger w-100"
                        onclick="return confirm('حذف الطلب نهائياً؟')">
                    <i class="fas fa-trash"></i> حذف الطلب
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

<!-- Approval Modal with Treasury and Category Selection -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-check-circle"></i> الموافقة على طلب الشراء</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('purchase-requests.approve', $purchaseRequest) }}" method="POST" onsubmit="return validateApproval()">
                @csrf

                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> حدد الخزائن التي سيتم صرف المبلغ منها والفئة المحاسبية
                    </div>

                    <!-- Request Summary -->
                    <div class="card mb-3" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">عنوان الطلب</p>
                                    <h6 style="margin: 5px 0 0; color: #333;">{{ $purchaseRequest->title }}</h6>
                                </div>
                                <div class="col-md-6">
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">المبلغ المراد صرفه</p>
                                    <h6 style="margin: 5px 0 0; color: #4caf50; font-weight: bold;">
                                        {{ number_format($purchaseRequest->estimated_cost ?? 0, 2) }} ج.م
                                    </h6>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Expense Category Selection -->
                    <div class="mb-3">
                        <label class="form-label"><strong>الفئة المحاسبية <span class="text-danger">*</span></strong></label>
                        <select name="expense_category_id" id="expenseCategorySelect" class="form-select @error('expense_category_id') is-invalid @enderror" required>
                            <option value="">-- اختر الفئة --</option>
                            @forelse($expenseCategories ?? [] as $category)
                                <option value="{{ $category->id }}">{{ $category->fullPath }}</option>
                            @empty
                                <option disabled>لا توجد فئات متاحة</option>
                            @endforelse
                        </select>
                        @error('expense_category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Primary Treasury Selection -->
                    <div class="mb-3">
                        <label class="form-label"><strong>الخزينة الرئيسية <span class="text-danger">*</span></strong></label>
                        <select name="treasury_id" id="primaryTreasurySelect" class="form-select @error('treasury_id') is-invalid @enderror"
                                onchange="updateTreasuryAmounts()" required>
                            <option value="">-- اختر الخزينة --</option>
                            @forelse($treasuries ?? [] as $treasury)
                                <option value="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}">
                                    {{ $treasury->name }} (الرصيد: {{ number_format($treasury->balance, 2) }} ج.م)
                                </option>
                            @empty
                                <option disabled>لا توجد خزائن متاحة</option>
                            @endforelse
                        </select>
                        @error('treasury_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Treasury Distribution -->
                    <div class="mb-3">
                        <label class="form-label"><strong>توزيع الصرف على الخزائن <span class="text-danger">*</span></strong></label>
                        <div id="treasuryDistribution" style="max-height: 300px; overflow-y: auto;">
                            <!-- Will be populated by JavaScript -->
                        </div>
                        @error('treasury_amounts')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Distribution Summary -->
                    <div class="card mb-3" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(13, 71, 161, 0.1)); border: 1px solid rgba(33, 150, 243, 0.3);">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">المبلغ المطلوب</p>
                                    <h5 id="requiredAmount" style="margin: 5px 0 0; color: #2196f3;">
                                        {{ number_format($purchaseRequest->estimated_cost ?? 0, 2) }} ج.م
                                    </h5>
                                </div>
                                <div class="col-md-6">
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">إجمالي المدخل</p>
                                    <h5 id="totalEntered" style="margin: 5px 0 0; color: #ff9800; font-weight: bold;">0.00 ج.م</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success" id="approveButton" disabled>
                        <i class="fas fa-check"></i> تأكيد الموافقة والصرف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const requiredAmount = parseFloat('{{ $purchaseRequest->estimated_cost ?? 0 }}');

function updateTreasuryAmounts() {
    const primaryTreasurySelect = document.getElementById('primaryTreasurySelect');
    const selectedTreasuryId = primaryTreasurySelect.value;
    const treasuryDistribution = document.getElementById('treasuryDistribution');

    if (!selectedTreasuryId) {
        treasuryDistribution.innerHTML = '';
        return;
    }

    const treasuries = @json($treasuries ?? []);
    const selectedTreasury = treasuries.find(t => t.id == selectedTreasuryId);

    if (!selectedTreasury) {
        treasuryDistribution.innerHTML = '';
        return;
    }

    let html = `
        <div class="input-group mb-2">
            <span class="input-group-text">${selectedTreasury.name}</span>
            <input type="number" name="treasury_amounts[${selectedTreasuryId}]" class="form-control treasury-amount"
                   step="0.01" min="0" max="${selectedTreasury.balance}" placeholder="0.00"
                   oninput="calculateTotal()">
            <span class="input-group-text">ج.م</span>
        </div>
    `;

    treasuryDistribution.innerHTML = html;
    calculateTotal();
}

function calculateTotal() {
    const inputs = document.querySelectorAll('.treasury-amount');
    let total = 0;

    inputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });

    const totalElement = document.getElementById('totalEntered');
    totalElement.textContent = total.toFixed(2) + ' ج.م';

    // Update button state
    const approveButton = document.getElementById('approveButton');
    const isValid = Math.abs(total - requiredAmount) < 0.01 && requiredAmount > 0;
    approveButton.disabled = !isValid;

    if (isValid) {
        totalElement.style.color = '#4caf50';
    } else if (total > requiredAmount) {
        totalElement.style.color = '#f44336';
    } else if (total > 0) {
        totalElement.style.color = '#ff9800';
    }
}

function validateApproval() {
    const categorySelect = document.getElementById('expenseCategorySelect');
    const treasurySelect = document.getElementById('primaryTreasurySelect');

    if (!categorySelect.value) {
        alert('يرجى اختيار الفئة المحاسبية');
        return false;
    }

    if (!treasurySelect.value) {
        alert('يرجى اختيار الخزينة');
        return false;
    }

    const inputs = document.querySelectorAll('.treasury-amount');
    let total = 0;
    inputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });

    if (Math.abs(total - requiredAmount) > 0.01) {
        alert('إجمالي المبالغ المدخلة يجب أن يساوي المبلغ المطلوب بالضبط');
        return false;
    }

    return true;
}

// Initialize on modal open
document.getElementById('approveModal').addEventListener('shown.bs.modal', function() {
    updateTreasuryAmounts();
});
</script>

@endsection
