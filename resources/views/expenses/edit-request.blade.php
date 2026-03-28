@extends('layouts.modern')

@section('content')
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h2>
                        <i class="fas fa-edit"></i> طلب تعديل المصروف
                    </h2>
                    <p class="text-muted">المصروف #{{ $expense->id }}</p>
                </div>
                <a href="{{ route('expenses.show', $expense) }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> رجوع
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- تحذير -->
            <div class="alert alert-warning mb-4">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>ملاحظة مهمة:</strong>
                سيتم إرسال طلب التعديل للمحاسب والمدير للموافقة عليه. بعد موافقة أحدهما، ستُطبَّق التغييرات تلقائياً ولن يتمكن أحد من تعديل المصروف إلا المدير.
            </div>

            <!-- بيانات المصروف الحالية -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">البيانات الحالية</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>المبلغ:</strong>
                            <p>{{ number_format($expense->amount, 2) }} ج.م</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>التاريخ:</strong>
                            <p>{{ $expense->expense_date ? $expense->expense_date->format('Y-m-d') : '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>الفئة:</strong>
                            <p>{{ $expense->category->name ?? '-' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <strong>البند:</strong>
                            <p>{{ $expense->item->name ?? '-' }}</p>
                        </div>
                        <div class="col-12 mb-3">
                            <strong>الوصف:</strong>
                            <p>{{ $expense->description }}</p>
                        </div>
                        @if($expense->location)
                            <div class="col-md-6 mb-3">
                                <strong>الموقع:</strong>
                                <p>{{ $expense->location }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- نموذج التعديل -->
            <form action="{{ route('expense-edit-requests.store', $expense) }}" method="POST" enctype="multipart/form-data" class="card">
                @csrf

                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">البيانات الجديدة (ما تريد تعديله)</h5>
                </div>
                <div class="card-body">
                    <!-- المبلغ -->
                    <div class="form-group mb-3">
                        <label for="amount" class="form-label">
                            <i class="fas fa-money-bill"></i> المبلغ
                            <span class="text-danger">*</span>
                        </label>
                        <input type="number" class="form-control @error('amount') is-invalid @enderror" id="amount"
                               name="amount" step="0.01" value="{{ old('amount', $expense->amount) }}" required>
                        @error('amount')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-1">
                            المبلغ الحالي: {{ number_format($expense->amount, 2) }} ج.م
                        </small>
                    </div>

                    <!-- الوصف -->
                    <div class="form-group mb-3">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left"></i> الوصف
                            <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description"
                                  name="description" rows="3" required>{{ old('description', $expense->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- الموقع -->
                    <div class="form-group mb-3">
                        <label for="location" class="form-label">
                            <i class="fas fa-map-marker-alt"></i> الموقع
                        </label>
                        <input type="text" class="form-control @error('location') is-invalid @enderror" id="location"
                               name="location" value="{{ old('location', $expense->location) }}">
                        @error('location')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- الفئة -->
                    <div class="form-group mb-3">
                        <label for="expense_category_id" class="form-label">
                            <i class="fas fa-list"></i> فئة المصروف
                            <span class="text-danger">*</span>
                        </label>
                        <select class="form-select @error('expense_category_id') is-invalid @enderror"
                                id="expense_category_id" name="expense_category_id" onchange="loadExpenseItems()" required>
                            <option value="">-- اختر فئة --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}"
                                        {{ old('expense_category_id', $expense->expense_category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('expense_category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- البند -->
                    <div class="form-group mb-3" id="item_group">
                        <label for="expense_item_id" class="form-label">
                            <i class="fas fa-tag"></i> البند
                        </label>
                        <select class="form-select @error('expense_item_id') is-invalid @enderror"
                                id="expense_item_id" name="expense_item_id">
                            <option value="">-- اختر بند --</option>
                            @if($expense->item)
                                <option value="{{ $expense->item->id }}" selected>
                                    {{ $expense->item->name }}
                                </option>
                            @endif
                        </select>
                        @error('expense_item_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- الحالة الاجتماعية -->
                    <div class="form-group mb-3" id="social_case_group">
                        <label for="social_case_id" class="form-label">
                            <i class="fas fa-user-circle"></i> الحالة الاجتماعية
                        </label>
                        <select class="form-select @error('social_case_id') is-invalid @enderror"
                                id="social_case_id" name="social_case_id">
                            <option value="">-- لا توجد --</option>
                            @foreach(\App\Models\SocialCase::where('status', 'approved')->get() as $case)
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

                    <!-- المرفق -->
                    <div class="form-group mb-3">
                        <label for="attachment" class="form-label">
                            <i class="fas fa-paperclip"></i> استبدال المرفق (اختياري)
                        </label>
                        <input type="file" class="form-control @error('attachment') is-invalid @enderror" id="attachment"
                               name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted d-block mt-1">
                            الملفات المسموحة: PDF, JPG, PNG, DOC, DOCX (الحد الأقصى: 2 ميجابايت)
                        </small>
                        @if($expense->attachment)
                            <small class="text-muted d-block mt-1">
                                المرفق الحالي موجود - يمكنك تحميل مرفق جديد لاستبداله
                            </small>
                        @endif
                        @error('attachment')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- السبب (اختياري) -->
                    <div class="form-group mb-3">
                        <label for="reason" class="form-label">
                            <i class="fas fa-comment"></i> السبب (ملاحظات إضافية)
                        </label>
                        <textarea class="form-control" id="reason" name="reason" rows="2"
                                  placeholder="اكتب السبب وراء طلب التعديل..."></textarea>
                    </div>
                </div>

                <div class="card-footer bg-light">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-send"></i> إرسال طلب التعديل
                    </button>
                    <a href="{{ route('expenses.show', $expense) }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                </div>
            </form>
        </div>

        <!-- السايدبار -->
        <div class="col-md-4">
            <!-- معلومات المصروف -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">معلومات المصروف</h5>
                </div>
                <div class="card-body">
                    <p><strong>المعرف:</strong> #{{ $expense->id }}</p>
                    <p><strong>التاريخ:</strong> {{ $expense->created_at->format('d/m/Y H:i') }}</p>
                    <p><strong>المستخدم:</strong> {{ $expense->user->name }}</p>
                    <p><strong>النوع:</strong> {{ $expense->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام' }}</p>
                    @if($expense->source)
                        <p><strong>المصدر:</strong> {{ $expense->source === 'custody' ? 'عهدة' : 'خزينة' }}</p>
                    @endif
                </div>
            </div>

            <!-- تعليمات -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">تعليمات</h5>
                </div>
                <div class="card-body">
                    <ol class="mb-0 ps-3">
                        <li class="mb-2">عدّل البيانات التي تريد تغييرها</li>
                        <li class="mb-2">اكتب ملاحظة إذا أردت (اختياري)</li>
                        <li class="mb-2">اضغط "إرسال الطلب"</li>
                        <li>سيتم إخطار المحاسب والمدير للموافقة</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadExpenseItems() {
    const categoryId = document.getElementById('expense_category_id').value;
    const itemSelect = document.getElementById('expense_item_id');

    if (!categoryId) {
        itemSelect.innerHTML = '<option value="">-- اختر بند --</option>';
        return;
    }

    // جلب البنود (يمكن استخدام AJAX هنا)
    fetch(`/api/expense-items?category_id=${categoryId}`)
        .then(response => response.json())
        .then(data => {
            itemSelect.innerHTML = '<option value="">-- اختر بند --</option>';
            data.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.name;
                itemSelect.appendChild(option);
            });
        });
}

// تحميل البنود عند التحميل
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('expense_category_id').value) {
        loadExpenseItems();
    }
});
</script>

<style>
.card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-control:focus, .form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}
</style>
@endsection
