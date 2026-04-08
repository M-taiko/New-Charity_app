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
                    <form action="{{ route('expenses.store') }}" method="POST" enctype="multipart/form-data">
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
                                        $remaining = $custody->getRemainingBalance();
                                    @endphp
                                    <option value="{{ $custody->id }}"
                                            data-remaining="{{ $remaining }}"
                                            {{ old('custody_id') == $custody->id ? 'selected' : '' }}>
                                        العهدة #{{ $custody->id }} - الوكيل: {{ $custody->agent->name }} (المتبقي: {{ number_format($remaining, 2) }} ج.م)
                                    </option>
                                @endforeach
                            </select>
                            @error('custody_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted" id="custody-info" style="display: none; margin-top: 0.5rem;">
                                المبلغ المتاح للصرف: <strong id="remaining-amount">0</strong> ج.م
                            </small>
                        </div>

                        <!-- Treasury Balance Display (shown when treasury source is selected) -->
                        @if($canSpendFromTreasury && $treasury)
                        <div class="mb-3" id="treasury-balance-field" style="display: none;">
                            <div class="alert alert-info" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1)); border: 1px solid rgba(79, 172, 254, 0.3);">
                                <h6 class="mb-2">
                                    <i class="fas fa-vault"></i> رصيد الخزينة
                                </h6>
                                <div style="font-size: 1.5rem; font-weight: 700; color: #4facfe;">
                                    {{ number_format($treasury->balance, 2) }} ج.م
                                </div>
                                <small class="text-muted">الرصيد المتاح للصرف المباشر</small>
                            </div>
                        </div>
                        @endif

                        <!-- Cascading 4-Level Category Selection -->
                        <div class="mb-2">
                            <label class="form-label fw-bold">
                                <i class="fas fa-sitemap"></i> التوجيه المحاسبي
                            </label>
                            <small class="text-muted d-block mb-2">اختر المستوى الأول ثم تحديد التوجيه النهائي</small>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted">المستوى الأول *</label>
                                <select id="cat_level1" class="form-select" onchange="catLoadChildren(1, this.value)">
                                    <option value="">-- اختر --</option>
                                    @foreach($categoryRoots as $root)
                                    <option value="{{ $root->id }}">{{ $root->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6" id="level2Wrap" style="display:none;">
                                <label class="form-label small text-muted">المستوى الثاني</label>
                                <select id="cat_level2" class="form-select" onchange="catLoadChildren(2, this.value)">
                                    <option value="">-- اختر --</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="level3Wrap" style="display:none;">
                                <label class="form-label small text-muted">المستوى الثالث (اختياري)</label>
                                <select id="cat_level3" class="form-select" onchange="catSetLevel3(this.value)">
                                    <option value="">-- بدون --</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="itemWrap" style="display:none;">
                                <label class="form-label small text-muted">البند النهائي *</label>
                                <select id="cat_item" name="expense_item_id"
                                        class="form-select @error('expense_item_id') is-invalid @enderror"
                                        onchange="setDefaultAmount(this)">
                                    <option value="">-- اختر بند --</option>
                                </select>
                                @error('expense_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted mt-1 d-block" id="default-amount-info"></small>
                            </div>
                        </div>
                        {{-- Hidden field for expense_category_id (set by JS to the deepest selected category) --}}
                        <input type="hidden" name="expense_category_id" id="final_category_id">

                        <!-- Expense Type Selection -->
                        <div class="mb-3">
                            <label class="form-label"><strong><i class="fas fa-tag"></i> نوع المصروف</strong></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" name="expense_type" id="type_social_case" value="social_case" class="btn-check" {{ old('expense_type') == 'social_case' ? 'checked' : '' }} onchange="toggleExpenseType()">
                                <label class="btn btn-outline-primary" for="type_social_case">
                                    <i class="fas fa-users"></i> حالة اجتماعية
                                </label>

                                <input type="radio" name="expense_type" id="type_general" value="general" class="btn-check" {{ old('expense_type', 'general') == 'general' ? 'checked' : '' }} onchange="toggleExpenseType()">
                                <label class="btn btn-outline-primary" for="type_general">
                                    <i class="fas fa-receipt"></i> مصروفات أخرى
                                </label>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <!-- Social Case Selection (shown only for social_case type) -->
                            <div class="col-md-6" id="social-case-field" style="display: none;">
                                <label class="form-label"><strong>الحالة الاجتماعية</strong></label>
                                <select name="social_case_id" id="social_case_select" class="form-select @error('social_case_id') is-invalid @enderror">
                                    <option value="">-- اختر حالة --</option>
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
                                <i class="fas fa-coins"></i> المبلغ (ج.م)
                            </strong></label>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" step="0.01" value="{{ old('amount') }}" required placeholder="أدخل المبلغ">
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2" id="balance-warning"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الوصف</strong></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2" required>{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <!-- Line Items (اختياري) -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0"><strong><i class="fas fa-list-ul"></i> تفاصيل البنود (اختياري)</strong></label>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addLineItem()">
                                    <i class="fas fa-plus"></i> إضافة بند
                                </button>
                            </div>
                            <div id="lineItemsContainer"></div>
                            <input type="hidden" name="line_items" id="lineItemsData">
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الموقع</strong></label>
                            <input type="text" name="location" class="form-control @error('location') is-invalid @enderror" value="{{ old('location') }}">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- File Attachment (Optional) -->
                        <div class="mb-3">
                            <label class="form-label">
                                <strong><i class="fas fa-paperclip"></i> إرفاق ملف (اختياري)</strong>
                            </label>
                            <div class="input-group">
                                <input type="file"
                                       name="attachment"
                                       id="attachmentInput"
                                       class="form-control @error('attachment') is-invalid @enderror"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                       onchange="updateFileLabel()">
                                <label class="input-group-text" for="attachmentInput" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border: none;">
                                    <i class="fas fa-upload"></i> اختر ملف
                                </label>
                                @error('attachment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle"></i> الملفات المسموحة: PDF, JPG, PNG, DOC, DOCX (حد أقصى: 2MB)
                            </small>
                            <div id="filePreview" style="display: none; margin-top: 1rem;">
                                <div class="alert alert-success" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(69, 160, 73, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fas fa-file-alt" style="color: #4caf50; font-size: 1.5rem;"></i>
                                            <span id="fileName" style="margin-right: 0.5rem; font-weight: 600;"></span>
                                            <small class="text-muted d-block" id="fileSize"></small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="clearFileInput()">
                                            <i class="fas fa-times"></i> إزالة
                                        </button>
                                    </div>
                                </div>
                            </div>
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

@push('scripts')
<script>
    // ──── Cascading 4-Level Category Logic ────
    async function catLoadChildren(fromLevel, parentId) {
        if (!parentId) {
            hideCatFrom(fromLevel + 1);
            document.getElementById('final_category_id').value = '';
            return;
        }
        const res = await fetch(`/api/expense-categories/${parentId}/children`);
        const children = await res.json();

        if (fromLevel === 1) {
            const sel2 = document.getElementById('cat_level2');
            sel2.innerHTML = '<option value="">-- اختر (اختياري) --</option>';
            children.forEach(c => sel2.innerHTML += `<option value="${c.id}">${c.name}</option>`);
            document.getElementById('level2Wrap').style.display = children.length ? '' : 'none';
            document.getElementById('level3Wrap').style.display = 'none';
            document.getElementById('itemWrap').style.display = 'none';
            document.getElementById('final_category_id').value = parentId;
            loadItemsFor(parentId);
        } else if (fromLevel === 2) {
            const sel3 = document.getElementById('cat_level3');
            sel3.innerHTML = '<option value="">-- بدون مستوى ثالث --</option>';
            children.forEach(c => sel3.innerHTML += `<option value="${c.id}">${c.name}</option>`);
            document.getElementById('level3Wrap').style.display = children.length ? '' : 'none';
            document.getElementById('final_category_id').value = parentId;
            loadItemsFor(parentId);
        }
    }

    function catSetLevel3(level3Id) {
        const level2Id = document.getElementById('cat_level2').value;
        const targetId = level3Id || level2Id;
        document.getElementById('final_category_id').value = targetId || '';
        if (targetId) loadItemsFor(targetId);
    }

    async function loadItemsFor(categoryId) {
        const res = await fetch(`/api/expense-categories/${categoryId}/items`);
        const items = await res.json();
        const wrap = document.getElementById('itemWrap');
        const sel = document.getElementById('cat_item');
        sel.innerHTML = '<option value="">-- اختر بند --</option>';
        if (items.length) {
            items.forEach(i => sel.innerHTML += `<option value="${i.id}" data-default="${i.default_amount || ''}">${i.name}</option>`);
            wrap.style.display = '';
        } else {
            wrap.style.display = 'none';
        }
    }

    function hideCatFrom(level) {
        if (level <= 2) { document.getElementById('level2Wrap').style.display = 'none'; }
        if (level <= 3) { document.getElementById('level3Wrap').style.display = 'none'; }
        document.getElementById('itemWrap').style.display = 'none';
    }

    function setDefaultAmount(sel) {
        const opt = sel ? sel.selectedOptions[0] : null;
        const info = document.getElementById('default-amount-info');
        const amountInput = document.querySelector('input[name="amount"]');
        if (opt && opt.dataset.default) {
            info.innerHTML = `<i class="fas fa-info-circle"></i> المبلغ الافتراضي: <strong>${parseFloat(opt.dataset.default).toFixed(2)} ج.م</strong>`;
            if (!amountInput.value) amountInput.value = opt.dataset.default;
        } else {
            info.innerHTML = '';
        }
    }

    // ──── Line Items Logic ────
    let lineItemCount = 0;

    function addLineItem() {
        lineItemCount++;
        const container = document.getElementById('lineItemsContainer');
        const row = document.createElement('div');
        row.className = 'd-flex gap-2 align-items-center mb-2';
        row.id = `li_${lineItemCount}`;
        row.innerHTML = `
            <input type="text" class="form-control" placeholder="الوصف" data-li="desc" style="flex:3;">
            <input type="number" class="form-control" placeholder="العدد" data-li="qty" min="1" style="flex:1;" oninput="updateLineItemsData()">
            <input type="number" class="form-control" placeholder="سعر الوحدة" data-li="price" min="0" step="0.01" style="flex:1.5;" oninput="updateLineItemsData()">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLineItem('li_${lineItemCount}')">
                <i class="fas fa-times"></i>
            </button>
        `;
        container.appendChild(row);
        row.querySelectorAll('input[data-li="desc"]').forEach(i => i.addEventListener('input', updateLineItemsData));
    }

    function removeLineItem(id) {
        document.getElementById(id)?.remove();
        updateLineItemsData();
    }

    function updateLineItemsData() {
        const rows = document.querySelectorAll('#lineItemsContainer > div');
        const items = [];
        rows.forEach(row => {
            const desc  = row.querySelector('[data-li="desc"]')?.value?.trim();
            const qty   = parseFloat(row.querySelector('[data-li="qty"]')?.value) || null;
            const price = parseFloat(row.querySelector('[data-li="price"]')?.value) || null;
            if (desc) items.push({ description: desc, quantity: qty, unit_price: price });
        });
        document.getElementById('lineItemsData').value = JSON.stringify(items);
    }

    function toggleSourceFields() {
        const source = document.querySelector('input[name="source"]:checked').value;
        const custodyField = document.getElementById('custody-field');
        const treasuryBalanceField = document.getElementById('treasury-balance-field');
        const balanceWarning = document.getElementById('balance-warning');

        if (source === 'treasury') {
            // Hide custody field
            custodyField.style.display = 'none';
            document.querySelector('select[name="custody_id"]').removeAttribute('required');

            // Show treasury balance
            if (treasuryBalanceField) {
                treasuryBalanceField.style.display = 'block';
            }

            // Display treasury balance info
            balanceWarning.innerHTML = '<i class="fas fa-info-circle"></i> الرصيد المتاح: <strong>{{ $treasury ? number_format($treasury->balance, 2) : '0.00' }} ج.م</strong>';
        } else {
            // Show custody field
            custodyField.style.display = 'block';
            document.querySelector('select[name="custody_id"]').setAttribute('required', 'required');

            // Hide treasury balance
            if (treasuryBalanceField) {
                treasuryBalanceField.style.display = 'none';
            }

            updateCustodyInfo();
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
                defaultAmountInfo.innerHTML = `<i class="fas fa-info-circle"></i> المبلغ الافتراضي: <strong>${parseFloat(defaultAmount).toLocaleString('ar-SA', {minimumFractionDigits: 2})} ج.م</strong>`;
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
            balanceWarning.innerHTML = `<i class="fas fa-info-circle"></i> الرصيد المتبقي: <strong>${parseFloat(remaining).toLocaleString('ar-SA', {minimumFractionDigits: 2})} ج.م</strong>`;
        } else {
            balanceWarning.innerHTML = '';
        }
    }

    // Toggle expense type (social case or general)
    function toggleExpenseType() {
        const expenseType = document.querySelector('input[name="expense_type"]:checked').value;
        const socialCaseField = document.getElementById('social-case-field');
        const socialCaseSelect = document.getElementById('social_case_select');

        if (expenseType === 'social_case') {
            socialCaseField.style.display = 'block';
            socialCaseSelect.setAttribute('required', 'required');
        } else {
            socialCaseField.style.display = 'none';
            socialCaseSelect.removeAttribute('required');
            socialCaseSelect.value = ''; // Clear selection
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateCustodyInfo();
        toggleExpenseType();
    });

    // File upload functions
    function updateFileLabel() {
        const input = document.getElementById('attachmentInput');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);

            // Check file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('حجم الملف يجب أن يكون أقل من 2 ميجابايت');
                clearFileInput();
                return;
            }

            fileName.textContent = file.name;
            fileSize.textContent = `الحجم: ${sizeInMB} ميجابايت`;
            filePreview.style.display = 'block';
        } else {
            filePreview.style.display = 'none';
        }
    }

    function clearFileInput() {
        const input = document.getElementById('attachmentInput');
        const filePreview = document.getElementById('filePreview');

        input.value = '';
        filePreview.style.display = 'none';
    }
</script>
@endpush
@endsection
