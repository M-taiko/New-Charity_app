@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1 style="margin:0;font-size:2rem;font-weight:700;"><i class="fas fa-plus-circle"></i> طلب شراء جديد</h1>
            <a href="{{ route('purchase-requests.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-right"></i> رجوع</a>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background:linear-gradient(135deg,#43e97b,#38f9d7);border:none;">
                    <h5 style="margin:0;color:white;"><i class="fas fa-shopping-cart"></i> بيانات الطلب</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('purchase-requests.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">عنوان الطلب <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" required placeholder="مثال: شراء طابعة للمكتب">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">الفئة <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    <option value="office_supplies" {{ old('category')=='office_supplies'?'selected':'' }}>مستلزمات مكتبية</option>
                                    <option value="equipment"       {{ old('category')=='equipment'?'selected':'' }}>معدات وأجهزة</option>
                                    <option value="services"        {{ old('category')=='services'?'selected':'' }}>خدمات</option>
                                    <option value="maintenance"     {{ old('category')=='maintenance'?'selected':'' }}>صيانة</option>
                                    <option value="other" selected  {{ old('category')=='other'?'selected':'' }}>أخرى</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">الأولوية <span class="text-danger">*</span></label>
                                <select name="priority" class="form-select" required>
                                    <option value="low"    {{ old('priority')=='low'?'selected':'' }}>منخفضة</option>
                                    <option value="medium" {{ old('priority','medium')=='medium'?'selected':'' }}>متوسطة</option>
                                    <option value="high"   {{ old('priority')=='high'?'selected':'' }}>عالية</option>
                                    <option value="urgent" {{ old('priority')=='urgent'?'selected':'' }}>عاجل</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">التكلفة التقديرية (ج.م)</label>
                                <input type="number" name="estimated_cost" class="form-control" step="0.01" min="0"
                                       value="{{ old('estimated_cost') }}" placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">مطلوب بحلول</label>
                                <input type="date" name="needed_by" class="form-control" value="{{ old('needed_by') }}">
                            </div>
                        </div>
                        @if($suppliers->isNotEmpty())
                        <div class="mb-3">
                            <label class="form-label fw-bold">المورد المقترح (اختياري)</label>
                            <select name="supplier_id" class="form-select">
                                <option value="">-- بدون مورد محدد --</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}" {{ old('supplier_id')==$s->id?'selected':'' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label fw-bold">تفاصيل الطلب</label>
                            <textarea name="description" class="form-control" rows="4"
                                      placeholder="اذكر المواصفات المطلوبة، السبب، أي تفاصيل مهمة...">{{ old('description') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">مرفق (اختياري)</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted">عرض سعر، صورة المنتج، إلخ. الحجم الأقصى 2MB</small>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> إرسال الطلب
                            </button>
                            <a href="{{ route('purchase-requests.index') }}" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
