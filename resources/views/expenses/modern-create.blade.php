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

                        <!-- Source Selection (for accountants only) -->
                        @if($canSpendFromTreasury)
                            <div class="mb-3">
                                <label class="form-label"><strong>مصدر الصرف</strong></label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" name="source" id="source_custody" value="custody" class="btn-check" checked onchange="toggleSourceFields()">
                                    <label class="btn btn-outline-primary" for="source_custody">
                                        <i class="fas fa-briefcase"></i> من العهدة
                                    </label>

                                    <input type="radio" name="source" id="source_treasury" value="treasury" class="btn-check" onchange="toggleSourceFields()">
                                    <label class="btn btn-outline-primary" for="source_treasury">
                                        <i class="fas fa-vault"></i> من الخزينة
                                    </label>
                                </div>
                            </div>
                        @else
                            <input type="hidden" name="source" value="custody">
                        @endif

                        <!-- Custody Selection (hidden when treasury is selected) -->
                        <div class="mb-3" id="custody-field">
                            <label class="form-label"><strong>اختر العهدة</strong></label>
                            <select name="custody_id" class="form-select @error('custody_id') is-invalid @enderror" onchange="updateCustodyInfo()">
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

                        <!-- Category Selection -->
                        <div class="mb-3">
                            <label class="form-label"><strong>
                                <i class="fas fa-list"></i> فئة المصروف
                            </strong></label>
                            <select name="expense_category_id" id="category_select" class="form-select @error('expense_category_id') is-invalid @enderror" required onchange="loadExpenseItems()">
                                <option value="">-- اختر فئة --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" data-items="{{ json_encode($category->items->map(fn($item) => ['id' => $item->id, 'name' => $item->name, 'default_amount' => $item->default_amount])) }}" {{ old('expense_category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Item Selection -->
                        <div class="mb-3">
                            <label class="form-label"><strong>
                                <i class="fas fa-tag"></i> البند
                            </strong></label>
                            <select name="expense_item_id" id="item_select" class="form-select @error('expense_item_id') is-invalid @enderror" required onchange="setDefaultAmount()">
                                <option value="">-- اختر بند --</option>
                            </select>
                            @error('expense_item_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2" id="default-amount-info"></small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>الحالة الاجتماعية (اختياري)</strong></label>
                                <select name="social_case_id" class="form-select @error('social_case_id') is-invalid @enderror">
                                    <option value="">-- لا توجد حالة --</option>
                                    @foreach($cases as $case)
                                        <option value="{{ $case->id }}" {{ old('social_case_id') == $case->id ? 'selected' : '' }}>{{ $case->name }}</option>
                                    @endforeach
                                </select>
                                @error('social_case_id')
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
                            <label class="form-label"><strong>
                                <i class="fas fa-coins"></i> المبلغ (ر.س)
                            </strong></label>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" step="0.01" value="{{ old('amount') }}" required placeholder="أدخل المبلغ">
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2" id="balance-warning"></small>
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
    // Get all categories data
    const categoriesData = {
        @foreach($categories as $category)
            {{ $category->id }}: {
                items: @json($category->items->map(fn($item) => ['id' => $item->id, 'name' => $item->name, 'default_amount' => $item->default_amount]))
            },
        @endforeach
    };

    function toggleSourceFields() {
        const source = document.querySelector('input[name="source"]:checked').value;
        const custodyField = document.getElementById('custody-field');

        if (source === 'treasury') {
            custodyField.style.display = 'none';
            document.querySelector('select[name="custody_id"]').removeAttribute('required');
            document.getElementById('balance-warning').innerHTML = '';
        } else {
            custodyField.style.display = 'block';
            document.querySelector('select[name="custody_id"]').setAttribute('required', 'required');
            updateCustodyInfo();
        }
    }

    function loadExpenseItems() {
        const categoryId = document.getElementById('category_select').value;
        const itemSelect = document.getElementById('item_select');
        const defaultAmountInfo = document.getElementById('default-amount-info');

        // Clear previous items
        itemSelect.innerHTML = '<option value="">-- اختر بند --</option>';
        defaultAmountInfo.innerHTML = '';

        if (categoryId && categoriesData[categoryId]) {
            const items = categoriesData[categoryId].items;
            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                option.setAttribute('data-default-amount', item.default_amount || '');
                if (item.id == {{ old('expense_item_id') }}) {
                    option.selected = true;
                }
                itemSelect.appendChild(option);
            });

            // Load default amount if item was selected
            setDefaultAmount();
        }
    }

    function setDefaultAmount() {
        const itemSelect = document.getElementById('item_select');
        const selectedOption = itemSelect.selectedOptions[0];
        const defaultAmountInfo = document.getElementById('default-amount-info');
        const amountInput = document.querySelector('input[name="amount"]');

        if (selectedOption && selectedOption.value) {
            const defaultAmount = selectedOption.getAttribute('data-default-amount');
            if (defaultAmount && defaultAmount !== '') {
                defaultAmountInfo.innerHTML = `<i class="fas fa-info-circle"></i> المبلغ الافتراضي: <strong>${parseFloat(defaultAmount).toLocaleString('ar-SA', {minimumFractionDigits: 2})} ر.س</strong>`;
                if (!amountInput.value) {
                    amountInput.value = defaultAmount;
                }
            }
        }
    }

    function updateCustodyInfo() {
        const custodySelect = document.querySelector('select[name="custody_id"]');
        const selectedOption = custodySelect.selectedOptions[0];
        const balanceWarning = document.getElementById('balance-warning');
        const amountInput = document.querySelector('input[name="amount"]');

        if (selectedOption && selectedOption.value) {
            const remaining = selectedOption.getAttribute('data-remaining');
            balanceWarning.innerHTML = `<i class="fas fa-info-circle"></i> الرصيد المتبقي: <strong>${parseFloat(remaining).toLocaleString('ar-SA', {minimumFractionDigits: 2})} ر.س</strong>`;
        } else {
            balanceWarning.innerHTML = '';
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadExpenseItems();
        updateCustodyInfo();
    });
</script>
@endsection
