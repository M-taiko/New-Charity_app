@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-edit"></i> طلب تعديل المصروف
                    </h1>
                    <p class="text-muted">المصروف #{{ $expense->id }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <!-- تحذير -->
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle"></i>
                <strong>ملاحظة:</strong> عدّل البيانات التي تريد تغييرها فقط. سيتم إرسال طلب التعديل للمحاسب والمدير للموافقة عليه.
            </div>

            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-edit"></i> بيانات المصروف الجديدة
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('expense-edit-requests.store', $expense) }}" method="POST" enctype="multipart/form-data">
                        @csrf

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
                                    <option value="{{ $root->id }}" {{ $expense->category->id == $root->id || $expense->category->parent?->id == $root->id || $expense->category->parent?->parent?->id == $root->id ? 'selected' : '' }}>{{ $root->name }}</option>
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
                                    @if($expense->item)
                                        <option value="{{ $expense->item->id }}" selected>{{ $expense->item->name }}</option>
                                    @endif
                                </select>
                                @error('expense_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted mt-1 d-block" id="default-amount-info"></small>
                            </div>
                        </div>
                        <input type="hidden" name="expense_category_id" id="final_category_id" value="{{ $expense->expense_category_id }}">

                        <!-- Expense Type Selection -->
                        <div class="mb-3">
                            <label class="form-label"><strong><i class="fas fa-tag"></i> نوع المصروف</strong></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" name="expense_type" id="type_social_case" value="social_case" class="btn-check" {{ $expense->type == 'social_case' ? 'checked' : '' }} onchange="toggleExpenseType()">
                                <label class="btn btn-outline-primary" for="type_social_case">
                                    <i class="fas fa-users"></i> حالة اجتماعية
                                </label>

                                <input type="radio" name="expense_type" id="type_general" value="general" class="btn-check" {{ $expense->type == 'general' ? 'checked' : '' }} onchange="toggleExpenseType()">
                                <label class="btn btn-outline-primary" for="type_general">
                                    <i class="fas fa-receipt"></i> مصروفات أخرى
                                </label>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6" id="social-case-field" style="display: {{ $expense->type == 'social_case' ? '' : 'none' }};">
                                <label class="form-label"><strong>الحالة الاجتماعية</strong></label>
                                <select name="social_case_id" id="social_case_select" class="form-select @error('social_case_id') is-invalid @enderror">
                                    <option value="">-- اختر حالة --</option>
                                    @foreach($cases as $case)
                                        <option value="{{ $case->id }}" {{ $expense->social_case_id == $case->id ? 'selected' : '' }}>{{ $case->name }}</option>
                                    @endforeach
                                </select>
                                @error('social_case_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><strong>تاريخ المصروف</strong></label>
                                <input type="date" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" required>
                                @error('expense_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>
                                <i class="fas fa-coins"></i> المبلغ (ج.م)
                            </strong></label>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" step="0.01" value="{{ old('amount', $expense->amount) }}" required placeholder="أدخل المبلغ">
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الوصف</strong></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="2" required>{{ old('description', $expense->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الموقع</strong></label>
                            <input type="text" name="location" class="form-control @error('location') is-invalid @enderror" value="{{ old('location', $expense->location) }}">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- File Attachment -->
                        <div class="mb-3">
                            <label class="form-label">
                                <strong><i class="fas fa-paperclip"></i> استبدال المرفق (اختياري)</strong>
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
                            @if($expense->attachment)
                                <small class="text-muted d-block mt-2">
                                    المرفق الحالي موجود - يمكنك تحميل مرفق جديد لاستبداله
                                </small>
                            @endif
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

                        <!-- ملاحظات -->
                        <div class="mb-3">
                            <label class="form-label"><strong>ملاحظات إضافية</strong></label>
                            <textarea name="reason" class="form-control @error('reason') is-invalid @enderror" rows="2" placeholder="اشرح سبب التعديل...">{{ old('reason') }}</textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                                <i class="fas fa-paper-plane"></i> إرسال طلب التعديل
                            </button>
                            <a href="{{ route('expenses.show', $expense) }}" class="btn btn-secondary">
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
                        <i class="fas fa-info-circle" style="color: #f5576c;"></i> البيانات الحالية
                    </h6>
                    <div style="font-size: 0.9rem; line-height: 1.8;">
                        <p><strong>المبلغ الحالي:</strong> {{ number_format($expense->amount, 2) }} ج.م</p>
                        <p><strong>التاريخ الحالي:</strong> {{ $expense->expense_date?->format('Y-m-d') }}</p>
                        <p><strong>الفئة الحالية:</strong> {{ $expense->category->name ?? '-' }}</p>
                        <p><strong>البند الحالي:</strong> {{ $expense->item->name ?? '-' }}</p>
                        <p><strong>الحالة الاجتماعية:</strong> {{ $expense->socialCase->name ?? '-' }}</p>
                    </div>
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

    // ──── Expense Type Toggle ────
    function toggleExpenseType() {
        const socialCaseField = document.getElementById('social-case-field');
        const type = document.querySelector('input[name="expense_type"]:checked')?.value;
        if (type === 'social_case') {
            socialCaseField.style.display = 'block';
        } else {
            socialCaseField.style.display = 'none';
        }
    }

    // ──── File Handling ────
    function updateFileLabel() {
        const input = document.getElementById('attachmentInput');
        const preview = document.getElementById('filePreview');
        if (input.files.length) {
            const file = input.files[0];
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = `${(file.size / 1024).toFixed(2)} KB`;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }

    function clearFileInput() {
        document.getElementById('attachmentInput').value = '';
        document.getElementById('filePreview').style.display = 'none';
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleExpenseType();
        const catLevel1 = document.getElementById('cat_level1');
        if (catLevel1.value) {
            catLoadChildren(1, catLevel1.value);
        }
    });
</script>
@endpush
@endsection
