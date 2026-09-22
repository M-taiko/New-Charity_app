@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-edit"></i> تعديل خزينة
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
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-vault"></i> {{ $treasury->name }}
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('treasury.update', $treasury) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">اسم الخزينة <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $treasury->name) }}"
                                   placeholder="مثال: فودافون كاش، إنستا باي، الخزينة الرئيسية" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">الملاحظات</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      id="notes" name="notes" rows="4"
                                      placeholder="أضف ملاحظات عن هذه الخزينة (اختياري)">{{ old('notes', $treasury->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                                    <div class="card-body text-center">
                                        <h6 style="color: #666; margin: 0;">الرصيد الحالي</h6>
                                        <h3 style="color: #4caf50; margin: 10px 0 0 0;">{{ number_format($treasury->balance, 2) }} ج.م</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(13, 71, 161, 0.1)); border: 1px solid rgba(33, 150, 243, 0.3);">
                                    <div class="card-body text-center">
                                        <h6 style="color: #666; margin: 0;">تاريخ الإنشاء</h6>
                                        <h3 style="color: #2196f3; margin: 10px 0 0 0;">{{ $treasury->created_at->format('Y-m-d') }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> حفظ التغييرات
                            </button>
                            <a href="{{ route('treasury.index') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
