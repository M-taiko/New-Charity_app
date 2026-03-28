@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-plus-circle"></i> {{ isset($expenseItem) ? 'تعديل البند' : 'إضافة بند جديد' }}
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-edit"></i> بيانات البند
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ isset($expenseItem) ? route('expense-items.update', $expenseItem) : route('expense-items.store') }}" method="POST">
                        @csrf
                        @if(isset($expenseItem))
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label class="form-label"><strong>الفئة <span class="text-danger">*</span></strong></label>
                            <select name="expense_category_id" class="form-select @error('expense_category_id') is-invalid @enderror" required>
                                <option value="">-- اختر فئة --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}"
                                            {{ (isset($expenseItem) && $expenseItem->expense_category_id == $category->id) || old('expense_category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>اسم البند <span class="text-danger">*</span></strong></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ isset($expenseItem) ? $expenseItem->name : old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الكود <span class="text-danger">*</span></strong></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ isset($expenseItem) ? $expenseItem->code : old('code') }}" required
                                   placeholder="مثال: ZAKAH_001">
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">كود فريد يميز البند</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><strong>المبلغ الافتراضي (اختياري)</strong></label>
                                    <div class="input-group">
                                        <input type="number" name="default_amount" class="form-control @error('default_amount') is-invalid @enderror"
                                               value="{{ isset($expenseItem) ? $expenseItem->default_amount : old('default_amount') }}"
                                               step="0.01" min="0" placeholder="0.00">
                                        <span class="input-group-text">ج.م</span>
                                    </div>
                                    @error('default_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <small class="text-muted">هذا المبلغ سيظهر عند اختيار البند</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><strong>الترتيب <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="order" class="form-control @error('order') is-invalid @enderror"
                                           value="{{ isset($expenseItem) ? $expenseItem->order : old('order', 1) }}" required min="1">
                                    @error('order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        @if(isset($expenseItem))
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="is_active"
                                       {{ $expenseItem->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">
                                    البند نشط ومتاح للاستخدام
                                </label>
                            </div>
                        </div>
                        @endif

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-save"></i> {{ isset($expenseItem) ? 'تحديث البند' : 'إضافة البند' }}
                            </button>
                            <a href="{{ route('expense-items.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 1px solid rgba(102, 126, 234, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #667eea;"></i> معلومات مهمة
                    </h6>
                    <ul style="font-size: 0.9rem; line-height: 1.8;">
                        <li>تأكد من إدخال كود فريد للبند</li>
                        <li>المبلغ الافتراضي يظهر عند اختيار البند</li>
                        <li>الترتيب يحدد موضع البند في القائمة المنسدلة</li>
                        <li>يمكن تعديل البيانات لاحقاً</li>
                        <li>الحذف سيزيل البند من جميع المصروفات</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
