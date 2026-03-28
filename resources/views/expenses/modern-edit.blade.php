@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-edit"></i> تعديل المصروف #{{ $expense->id }}
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        تعديل مباشر بواسطة {{ auth()->user()->hasRole('مدير') ? 'المدير' : 'المحاسب' }}
                    </p>
                </div>
                <a href="{{ route('expenses.show', $expense) }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-pencil-alt"></i> بيانات المصروف
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('expenses.update', $expense) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <!-- Category Selection -->
                        <div class="mb-3">
                            <label class="form-label"><strong>
                                <i class="fas fa-list"></i> فئة المصروف
                            </strong></label>
                            <select name="expense_category_id" id="category_select" class="form-select @error('expense_category_id') is-invalid @enderror" required onchange="loadExpenseItems()">
                                <option value="">-- اختر فئة --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}"
                                            data-code="{{ $category->code }}"
                                            data-items="{{ json_encode($category->items->map(fn($item) => ['id' => $item->id, 'name' => $item->name, 'default_amount' => $item->default_amount])) }}"
                                            {{ old('expense_category_id', $expense->expense_category_id) == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Item Selection -->
                        <div class="mb-3" id="item-field">
                            <label class="form-label"><strong>
                                <i class="fas fa-tag"></i> البند
                            </strong></label>
                            <select name="expense_item_id" id="item_select" class="form-select @error('expense_item_id') is-invalid @enderror">
                                <option value="">-- اختر بند --</option>
                            </select>
                            @error('expense_item_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2" id="default-amount-info"></small>
                        </div>

                        <!-- Expense Type -->
                        <div class="mb-3">
                            <label class="form-label"><strong><i class="fas fa-tag"></i> نوع المصروف</strong></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" name="expense_type" id="type_social_case" value="social_case" class="btn-check"
                                    {{ old('expense_type', $expense->type) == 'social_case' ? 'checked' : '' }}
                                    onchange="toggleExpenseType()">
                                <label class="btn btn-outline-primary" for="type_social_case">
                                    <i class="fas fa-users"></i> حالة اجتماعية
                                </label>

                                <input type="radio" name="expense_type" id="type_general" value="general" class="btn-check"
                                    {{ old('expense_type', $expense->type) == 'general' ? 'checked' : '' }}
                                    onchange="toggleExpenseType()">
                                <label class="btn btn-outline-primary" for="type_general">
                                    <i class="fas fa-receipt"></i> مصروفات أخرى
                                </label>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <!-- Social Case -->
                            <div class="col-md-6" id="social-case-field" style="{{ old('expense_type', $expense->type) == 'social_case' ? '' : 'display:none;' }}">
                                <label class="form-label"><strong>الحالة الاجتماعية</strong></label>
                                <select name="social_case_id" id="social_case_select" class="form-select @error('social_case_id') is-invalid @enderror">
                                    <option value="">-- اختر حالة --</option>
                                    @foreach($cases as $case)
                                        <option value="{{ $case->id }}"
                                            {{ old('social_case_id', $expense->social_case_id) == $case->id ? 'selected' : '' }}>
                                            {{ $case->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('social_case_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label"><strong>تاريخ المصروف</strong></label>
                                <input type="date" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror"
                                    value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" required>
                                @error('expense_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="mb-3">
                            <label class="form-label"><strong>
                                <i class="fas fa-coins"></i> المبلغ (ج.م)
                            </strong></label>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                                step="0.01" value="{{ old('amount', $expense->amount) }}" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">المبلغ الأصلي: <strong>{{ number_format($expense->amount, 2) }} ج.م</strong></small>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label"><strong>الوصف</strong></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" required>{{ old('description', $expense->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Location -->
                        <div class="mb-3">
                            <label class="form-label"><strong>الموقع</strong></label>
                            <input type="text" name="location" class="form-control @error('location') is-invalid @enderror"
                                value="{{ old('location', $expense->location) }}">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Attachment -->
                        <div class="mb-3">
                            <label class="form-label">
                                <strong><i class="fas fa-paperclip"></i> إرفاق ملف (اختياري)</strong>
                            </label>
                            @if($expense->attachment)
                                <div class="alert alert-info mb-2" style="padding: 0.5rem 1rem;">
                                    <i class="fas fa-file-alt"></i> يوجد مرفق حالي:
                                    <strong>{{ basename($expense->attachment) }}</strong>
                                    <small class="text-muted d-block">رفع ملف جديد سيستبدل المرفق الحالي</small>
                                </div>
                            @endif
                            <div class="input-group">
                                <input type="file"
                                       name="attachment"
                                       id="attachmentInput"
                                       class="form-control @error('attachment') is-invalid @enderror"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                       onchange="updateFileLabel()">
                                <label class="input-group-text" for="attachmentInput" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border: none;">
                                    <i class="fas fa-upload"></i> اختر ملف
                                </label>
                                @error('attachment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div id="filePreview" style="display: none; margin-top: 1rem;">
                                <div class="alert alert-success">
                                    <i class="fas fa-file-alt" style="color: #4caf50;"></i>
                                    <span id="fileName" style="margin-right: 0.5rem; font-weight: 600;"></span>
                                    <small class="text-muted d-block" id="fileSize"></small>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> حفظ التعديلات
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
            <div class="card mb-3" style="background: linear-gradient(135deg, rgba(67, 233, 123, 0.1), rgba(56, 249, 215, 0.1)); border: 1px solid rgba(67, 233, 123, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #38f9d7;"></i> معلومات المصروف الحالي
                    </h6>
                    <table class="table table-sm table-borderless" style="font-size: 0.9rem;">
                        <tr>
                            <td class="text-muted">المبلغ:</td>
                            <td><strong>{{ number_format($expense->amount, 2) }} ج.م</strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">التاريخ:</td>
                            <td>{{ $expense->expense_date?->format('Y-m-d') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">المصدر:</td>
                            <td>{{ $expense->source === 'treasury' ? 'الخزينة' : 'العهدة' }}</td>
                        </tr>
                        @if($expense->approval_status === 'approved')
                        <tr>
                            <td class="text-muted">الحالة:</td>
                            <td><span class="badge bg-success">معتمد</span></td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if($expense->isApproved())
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>تنبيه:</strong> هذا المصروف معتمد. أي تعديل سيغير حالته.
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    const categoriesData = {
        @foreach($categories as $category)
            {{ $category->id }}: {
                code: '{{ $category->code }}',
                items: @json($category->items->map(fn($item) => ['id' => $item->id, 'name' => $item->name, 'default_amount' => $item->default_amount]))
            },
        @endforeach
    };

    const currentItemId = {{ $expense->expense_item_id ?? 'null' }};

    function loadExpenseItems() {
        const categorySelect = document.getElementById('category_select');
        const categoryId = categorySelect.value;
        const itemSelect = document.getElementById('item_select');
        const itemField = document.getElementById('item-field');

        itemSelect.innerHTML = '<option value="">-- اختر بند --</option>';

        if (categoryId && categoriesData[categoryId]) {
            const categoryCode = categoriesData[categoryId].code;

            if (categoryCode === 'OTHER') {
                itemField.style.display = 'none';
                itemSelect.removeAttribute('required');
            } else {
                itemField.style.display = 'block';
                itemSelect.setAttribute('required', 'required');

                const items = categoriesData[categoryId].items;
                items.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    option.setAttribute('data-default-amount', item.default_amount || '');
                    if (currentItemId && item.id == currentItemId) {
                        option.selected = true;
                    }
                    itemSelect.appendChild(option);
                });
            }
        }
    }

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
            socialCaseSelect.value = '';
        }
    }

    function updateFileLabel() {
        const input = document.getElementById('attachmentInput');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.size > 2 * 1024 * 1024) {
                alert('حجم الملف يجب أن يكون أقل من 2 ميجابايت');
                input.value = '';
                filePreview.style.display = 'none';
                return;
            }
            fileName.textContent = file.name;
            fileSize.textContent = `الحجم: ${(file.size / (1024 * 1024)).toFixed(2)} ميجابايت`;
            filePreview.style.display = 'block';
        } else {
            filePreview.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadExpenseItems();
        toggleExpenseType();
    });
</script>
@endpush
@endsection
