@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1 style="margin:0;font-size:2rem;font-weight:700;"><i class="fas fa-tools"></i> إبلاغ عن مشكلة صيانة</h1>
            <a href="{{ route('maintenance-requests.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> رجوع</a>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header" style="background:linear-gradient(135deg,#f5576c,#f093fb);border:none;">
                    <h5 style="margin:0;color:white;"><i class="fas fa-exclamation-triangle"></i> تفاصيل المشكلة</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('maintenance-requests.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">عنوان المشكلة <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" required placeholder="مثال: تعطل جهاز التكييف في القاعة">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">الموقع</label>
                                <input type="text" name="location" class="form-control" value="{{ old('location') }}"
                                       placeholder="مثال: الدور الثاني، مكتب المدير">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">الأولوية <span class="text-danger">*</span></label>
                                <select name="priority" class="form-select" required>
                                    <option value="low"    {{ old('priority')=='low'?'selected':'' }}>منخفضة</option>
                                    <option value="medium" {{ old('priority','medium')=='medium'?'selected':'' }}>متوسطة</option>
                                    <option value="high"   {{ old('priority')=='high'?'selected':'' }}>عالية</option>
                                    <option value="urgent" {{ old('priority')=='urgent'?'selected':'' }}>عاجل — يعيق العمل</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">وصف المشكلة</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="اشرح المشكلة بالتفصيل...">{{ old('description') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">صورة أو مستند (اختياري)</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">الحجم الأقصى 2MB</small>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-paper-plane"></i> إرسال الطلب
                            </button>
                            <a href="{{ route('maintenance-requests.index') }}" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
